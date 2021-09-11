<?php
/**
 * WebSocket Server Library
 * 
 * @package FormsFramework
 * @subpackage Libs
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://www.ffphp.com
 */

namespace FF\Libs\PWA\WebSocket\Server;
use FF\Libs\PWA\WebSocket\Common as WebSocketCommon;
use FF\Core\Common;
use FF\Libs\PWA\WebSocket\Common\Log;

abstract class Websocket_base
{
	use WebSocketCommon\Errors;
	
	public mixed $sock = null;

	public ?float 				$time_connected		= null;
	public ?float 				$time_last_rcv		= null;
	public ?float 				$time_last_snd		= null;
	public ?float 				$time_last_message	= null;

	public 	mixed 				$sent_ping_data 	= null;
	public 	?float 				$sent_ping_time 	= null;
	public 	int 				$ping_count			= 0;
	public 	bool 				$ping_pending 		= false;

	private ?string $id = null;
	private string|false|null $ip;
	
	protected mixed $last_rcv_payload = null;
	/**
	 * @var array<string, string>
	 */
	protected array $headers = [];
	protected ?Server_base $server = null;
	
	private bool $handshake_done = false;
	private ?string $buffer_read = null;
	private ?string	$buffer_payload = null;

	private ?int $last_opcode_type = null;
	
	private ?Client_base $client = null;

	public function __construct($sock, $server)
	{
		$this->sock		= $sock;
		$this->server	= $server;
		
		$this->ip = stream_socket_get_name($sock, true);
		$this->time_connected = microtime(true);
	}

	function getLog(): ?Log
	{
		return Log::get($this->server->log_sockets);
	}

	public function setID($id)
	{
		$this->id = $id;
	}
	
	public function getID(): ?string
	{
		return $this->id;
	}
	
	public function getIP(): bool|string|null
	{
		return $this->ip;
	}
	
	public function getHeader($name): ?string
	{
		if (!isset($this->headers[$name]))
			return null;
		else
			return $this->headers[$name];
	}
	
	public function isHandshakeDone(): bool
	{
		return $this->handshake_done;
	}
	
	public function receive($raw_data, $bytes)
	{
		$this->buffer_read .= $raw_data;
		
		while(true)
		{
			if (!$this->handshake_done)
			{
				$rc = $this->process_handshake($this->buffer_read, strlen($this->buffer_read));
			}
			else
			{
				$rc = $this->process_frame($this->buffer_read, strlen($this->buffer_read));
				if ($rc === true)
				{
					$this->getLog()?->out(
						text: "Received Message",
						entity_id: $this->getID(),
						additional_data: $this->server->log_payloads ? $this->last_rcv_payload : null,
					);
					$this->client->onMessage($this->last_opcode_type, $this->last_rcv_payload);
				}
			}

			if ($rc !== true || !strlen($this->buffer_read))
				return $rc;

			$this->getLog()?->out(
				text: "Some data left, processing frame",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
		}
	}
	
	private function process_handshake($raw_data, $bytes): ?bool
	{
		$this->resetError();
		$this->getLog()?->out(
			text: "Checking handshake..",
			entity_id: $this->getID(),
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		
		/* check if we are dealing with a well-formed content */
		if (!str_contains($raw_data, "\n"))
		{
			$this->getLog()?->out(
				text: "Rawdata incomplete, postponing",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
			return null;
		}
		
		$skip_last_line = !str_ends_with($raw_data, "\n");
				
		$lines = explode("\n", $raw_data);
		
		//print_r($lines);
		
		$processed_headers = [];
		$offset_data = 0;
		$line_count = -1;
		$handshake_done = false;
		
		try
		{
			for ($i = 0; $i < count($lines); $i++)
			{
				if ($skip_last_line && ($i + 1) === count($lines))
					break;
				
				$line = $lines[$i];
				$offset_data += strlen($line) + (($i + 1) < count($lines) ? 1 : 0); // add the \n character to the count for every line except the last

				if (trim($line) === "") /* headers end is marked with an empty string */
				{
					$handshake_done = true;
					break;
				}

				$line_count++;

				if ($line_count === 0)
				{
					$rc = preg_match("/([^ ]+) ([^ ]+) HTTP\/([\d.]+)/", $line, $matches);
					if (!$rc)
						throw new Common\NonCriticalException("Protocol error on line #" . $i . ": Request-Line missing, found " . var_export($line, true));
					if ($matches[1] !== "GET")
						throw new Common\NonCriticalException("Protocol error on line #" . $i . ": Request-Line method must be GET, found " . var_export($matches[1], true));
					if (version_compare($matches[3], "1.1", "<"))
						throw new Common\NonCriticalException("Protocol error on line #" . $i . ": Request-Line http version must be >= 1.1, found " . $matches[3]);
					
					$url_parts = parse_url($matches[2]);
					if (isset($url_parts["host"]))
						throw new Common\NonCriticalException("Protocol error on line #" . $i . ": Request URI must be relative, found " . $matches[2]);
					
					$processed_headers["Request-Line"] = [
						"method"		=> $matches[1],
						"request_uri"	=> $matches[2],
						"url_parts"		=> $url_parts,
						"http_version"	=> $matches[3]
					];
				}
				else
				{
					$rc = preg_match("/([^:]+):\s*(.*)/", $line, $matches);
					if (!$rc)
						throw new Common\NonCriticalException("Protocol error on line #" . $i . ": Header malformed, found " . var_export($line));

					$tmp_name = trim($matches[1]);
					$tmp_value = trim($matches[2]);
					$tmp_params = explode(";", $tmp_value);
					if (count($tmp_params) === 1)
					{
						$processed_headers[$tmp_name] = $tmp_value;
					}
					else
					{
						$processed_headers[$tmp_name] = [];
						foreach ($tmp_params as $tmp_param)
						{
							$tmp_subparts = explode("=", $tmp_param);
							if (count($tmp_subparts) > 1)
							{
								$processed_headers[$tmp_name][trim($tmp_subparts[0])] = trim($tmp_subparts[1]);
							}
							else
							{
								$processed_headers[$tmp_name][trim($tmp_param)] = null;
							}
						}
					}
				}
			}
			
			if (!$handshake_done)
			{
				// postpone the processing for a next buffer reading
				$this->getLog()?->out(
					text: "Rawdata incomplete, postponing",
					entity_id: $this->getID(),
					level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
				);
				return null;
			}
			
			$missing_headers = array_diff(["Host", "Upgrade", "Connection", "Sec-WebSocket-Key", "Origin", "Sec-WebSocket-Version"], array_keys($processed_headers));
			if (count($missing_headers))
				throw new Common\NonCriticalException("Protocol error: missing headers: " . implode(", ", $missing_headers));

			if (!in_array($processed_headers["Host"], $this->server->allowed_hosts))
				throw new Common\NonCriticalException("Protocol error: Host " . $processed_headers["Host"] . " is not allowed");

			if (!str_contains($processed_headers["Upgrade"], "websocket"))
				throw new Common\NonCriticalException("Protocol error: Upgrade header does not contain the keyword websocket");

			if (!str_contains($processed_headers["Connection"], "Upgrade"))
				throw new Common\NonCriticalException("Protocol error: Connection header does not contain the token Upgrade");

			if (!in_array($processed_headers["Origin"], $this->server->allowed_origins))
				throw new Common\NonCriticalException("Protocol error: Origin " . $processed_headers["Origin"] . " is not allowed");

			if ($processed_headers["Sec-WebSocket-Version"] !== "13")
				throw new Common\NonCriticalException("Protocol error: WebSocket Version 13 supported, " . $processed_headers["Sec-WebSocket-Version"] . " found");
			
			/* all good, shake the hand back */
			$this->getLog()?->out(
				text: "..all good, shaking back..",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
			
			$sec_websocket_accept = base64_encode(sha1($processed_headers["Sec-WebSocket-Key"] . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

			$response = "HTTP/1.1 101 Switching Protocols\n"
						. "Upgrade: websocket\n"
						. "Connection: Upgrade\n"
						. "Sec-WebSocket-Accept: " . $sec_websocket_accept . "\n\n";
			
			$rc = @fwrite($this->sock, $response, strlen($response));
			fflush($this->sock);
			if ($rc === false)
			{
				throw new Common\NonCriticalException("Unable to send handshake response");
			}

			$this->handshake_done = true;
			$this->headers = $processed_headers;
			$this->buffer_read = substr($raw_data, $offset_data); // recover leftovers, if any
			
			$this->getLog()?->out(
				text: "..handshake done",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
			
			$rc = $this->server->route($this, $url_parts["path"], $url_parts["query"] ?? "");
			if ($rc === false)
			{
				$this->disconnect();
				return false;
			}
			
			$this->client = $rc;
			
			$rc = $this->client->onOpen();
			if ($rc === false)
			{
				$this->disconnect();
				return false;
			}
			
			return true;
		}
		catch (Common\NonCriticalException $e)
		{
			$this->setError(
				code: WebSocketCommon\ERROR_CLIENT_HANDSHAKE,
				additional_data: $e->getMessage(),
				exception: false,
				entity_id: $this->getID()
			);
			$this->disconnect();
			return false;
		}
	}

	private function process_frame($raw_data, $bytes): ?bool
	{
		$this->resetError();
		$this->getLog()?->out(
			text: "Checking frame...",
			entity_id: $this->getID(),
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		
		if ($bytes < 2)
		{
			$this->getLog()?->out(
				text: "Frame incomplete, postponing",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
			return null;
		}
		
		try
		{
			$byte_1	= ord($raw_data[0]);

			// get messages flags and type
			$flag_fin	= $byte_1 & 0x80 && true;
			$flag_rsv1	= $byte_1 & 0x40 && true;
			$flag_rsv2	= $byte_1 & 0x20 && true;
			$flag_rsv3	= $byte_1 & 0x10 && true;
			
			$opcode = $byte_1 & 0x0F; // only the first nibble
			$opcode_type = ($opcode >= 0x3 && $opcode <= 0x7) || ($opcode >= 0xB && $opcode <= 0xF) ? WebSocketCommon\PROT_OPCODE_RESERVED : $opcode;

			$this->getLog()?->out(
				text: "flag_fin = $flag_fin",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_TRACE
			);
			$this->getLog()?->out(
				text: "Opcode = $opcode (" . WebSocketCommon\PROT_OPCODE_DESCR[$opcode_type] . ")",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_TRACE
			);
			
			if($flag_rsv1 || $flag_rsv2 || $flag_rsv3)
			{
				throw new Common\NonCriticalException("ERROR: Unknown RSV<$flag_rsv1><$flag_rsv2><$flag_rsv3> flag set!");
			}

			$flag_control = $opcode & 0x8 && true;
			
			// fragmentation sanity check
			if ($flag_fin) // fragmentation bit
			{
				if (!$flag_control)
				{
					if ($opcode_type === WebSocketCommon\PROT_OPCODE_CONTINUATION) // last fragment
					{
						if ($this->buffer_payload === null)
							throw new Common\NonCriticalException("ERROR: last fragment without previous ones");
					}
					else // unfragmented message
					{
						if ($this->buffer_payload !== null)
							throw new Common\NonCriticalException("ERROR: starting a new unfragmented message with a previous one incomplete");
					}
				}
			}
			else
			{
				if ($flag_control)
					throw new Common\NonCriticalException("ERROR: control frames must not be fragmented");
				
				if ($opcode_type === WebSocketCommon\PROT_OPCODE_CONTINUATION) // subsequent fragments
				{
					if ($this->buffer_payload === null)
						throw new Common\NonCriticalException("ERROR: fragment continuation without previous ones");
				}
				else // first fragment
				{
					if ($this->buffer_payload !== null)
						throw new Common\NonCriticalException("ERROR: starting a new fragmented message with a previous one incomplete");
				}
			}

			$payload = "";

			$byte_2	= ord($raw_data[1]);
			
			// Determine mask status
			$frame_is_masked = $byte_2 & 0x80 && true;
			$this->getLog()?->out(
				text: "Masked = $frame_is_masked",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_TRACE
			);

			// Determine payload length
			$payload_length = $byte_2 & 127; // remove the mask bit if present with a logical and on the other bits
			$length_offset = 0;
			if ($payload_length === 126)
			{
				if ($flag_control)
					throw new Common\NonCriticalException("ERROR: control frames length must be <= 125");
					
				if (strlen($raw_data) < 4)
				{
					$this->getLog()?->out(
						text: "Frame incomplete, postponing",
						entity_id: $this->getID(),
						level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
					);
					return null;
				}
				
				$payload_length = unpack("nuint16", substr($raw_data, 2, 2))["uint16"]; // network order = big endian, so "n"
				$length_offset = 2;
			}
			elseif ($payload_length === 127)
			{
				if ($flag_control)
					throw new Common\NonCriticalException("ERROR: control frames length must be <= 125");
					
				if (strlen($raw_data) < 10)
				{
					$this->getLog()?->out(
						text: "Frame incomplete, postponing",
						entity_id: $this->getID(),
						level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
					);
					return null;
				}
				
				$payload_length = unpack("Juint64", substr($raw_data, 2, 8))["uint64"];
				$length_offset = 8;
			}

			$this->getLog()?->out(
				text: "Payload Length = $payload_length",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_TRACE
			);

			# Getting/decoding Payload
			$payload_offset = 0;
			
			if ($payload_length)
			{
				if ($frame_is_masked) # Masked frame (client to server)
				{
					$mask_offset = 2 + $length_offset;
					$payload_offset = $mask_offset + 4;

					if (strlen($raw_data) < $payload_offset + $payload_length)
					{
						$this->getLog()?->out(
							text: "Frame incomplete, postponing [" . strlen($raw_data) . "/" . ($payload_offset + $payload_length) . "]",
							entity_id: $this->getID(),
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
						);
						return null;
					}

					# Now we extract the mask and payload
					$mask = substr($raw_data, $mask_offset, 4);
					$encoded_payload = substr($raw_data, $payload_offset);

					# Finally, we decode the encoded frame payload
					for ($i = 0; $i < $payload_length; $i++)
					{
						$payload .= $encoded_payload[$i] ^ $mask[$i % 4];
					}
				}
				else # Unmasked frame (server to client)
				{
					$payload_offset = 2 + $length_offset;

					if (strlen($raw_data) < $payload_offset + $payload_length)
					{
						$this->getLog()?->out(
							text: "Frame incomplete, postponing",
							entity_id: $this->getID(),
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
						);
						return null;
					}

					# Read the payload
					$payload = substr($raw_data, $payload_offset);
				}
			}
			else
			{
				$payload = "";
			}
			
			// if we arrived here, the frame is fully come
			$this->buffer_read = substr($raw_data, $payload_offset + $payload_length); // recover leftovers, if any
			
			if ($flag_control) // control messages should be managed independently of the fragment sequence
			{
				switch($opcode_type)
				{
					case WebSocketCommon\PROT_OPCODE_CONN_CLOSE:
						$code = null;
						$status = null;
							
						if ($payload_length > 0)
						{
							$code = unpack("nuint16", substr($payload, 0, 2))["uint16"]; // network order = big endian, so "n"
							if ($payload_length > 2)
								$status = substr($payload, 2);

						}
						$tmp = "CLOSE message, disconnecting";
						if ($code !== null)
						{
							$tmp .= " with code " . $code;
							if (isset(WebSocketCommon\PROT_CLOSE_CODE_DESCR[$code]))
								$tmp .= " [" . WebSocketCommon\PROT_CLOSE_CODE_DESCR[$code] . "]";
						}
						if ($status !== null)
						{
							$tmp .=  ($code ? " and" : " with") . " message: " . $status;
						}
						
						$this->getLog()?->out(
							$tmp,
							entity_id: $this->getID(),
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
						);
						$this->disconnect();
						return false;
						
					case WebSocketCommon\PROT_OPCODE_PING:
						$this->getLog()?->out(
							text: "PING, answering back with a pong" . ($payload_length ? " with payload: " . $payload : ""),
							entity_id: $this->getID(),
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
						);
						$this->sendPong($payload);
						return null;
					
					case WebSocketCommon\PROT_OPCODE_PONG:
						if ($this->ping_pending)
						{
							$this->ping_pending = false;

							if ($payload !== $this->sent_ping_data)
								throw new Common\NonCriticalException("ERROR! PONG received with unmatched data. Sent: " . $this->sent_ping_data . " Received: " . $payload);
							$this->getLog()?->out(
								text: "PONG received in answer with matching payload to ping sent at " . date("d/m/Y H:i:s.u", $this->sent_ping_time),
								entity_id: $this->getID(),
								level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
							);
						}
						else
						{
							$this->getLog()?->out(
								text: "PONG received without asking for it, ignoring",
								entity_id: $this->getID(),
								level: WebSocketCommon\constLogLevels::LOG_LEVEL_WARN
							);
						}
						return null;
					
					default:
						$this->getLog()?->out(
							text: "Unknown control message type, ignoring it",
							entity_id: $this->getID(),
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_WARN
						);
						return null;
				}
			}
			else
			{
				// managing fragmentation
				if ($flag_fin) // fragmentation bit
				{
					if ($opcode_type === WebSocketCommon\PROT_OPCODE_CONTINUATION) // last fragment
					{
						$payload = $this->buffer_payload . $payload;
						$this->buffer_payload = null;
					}
					else // unfragmented message
					{
						// nothing to do
					}
				}
				else
				{
					if ($opcode_type === WebSocketCommon\PROT_OPCODE_CONTINUATION) // subsequent fragments
					{
						$this->buffer_payload .= $payload;
						$payload = null;
					}
					else // first fragment
					{
						$this->buffer_payload .= $payload;
						$payload = null;
					}
				}


				if ($opcode_type != WebSocketCommon\PROT_OPCODE_CONTINUATION)
				{
					$this->last_opcode_type = $opcode_type;
				}

				if ($payload !== null) // normal message, completed
				{
					$this->time_last_message = microtime(true);
					$this->ping_count = 0;

					$this->last_rcv_payload = $payload;
					return true;
				}
				else
				{
					$this->getLog()?->out(
						text: "Fragmented message incomplete, postponing",
						entity_id: $this->getID(),
						level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
					);
					return null;
				}
			}
		}
		catch (Common\NonCriticalException $e)
		{
			$this->setError(
				code: WebSocketCommon\ERROR_CLIENT_FRAME,
				additional_data: $e->getMessage(),
				exception: false,
				entity_id: $this->getID()
			);
			$this->client->onError(WebSocketCommon\ERROR_CLIENT_FRAME, $e->getMessage());
			$this->disconnect();
			return false;
		}
	}
	
	public function sendPong($payload): bool
	{
		return $this->sendMessage(WebSocketCommon\PROT_OPCODE_PONG, false, $payload);
	}
	
	public function sendPing($payload): bool
	{
		if (!$this->ping_pending)
		{
			$this->ping_pending = true;

			$this->sent_ping_data = $payload;
			$this->sent_ping_time = microtime(true);
			$this->ping_count++;

			return $this->sendMessage(WebSocketCommon\PROT_OPCODE_PING, false, $payload);
		}
		else
		{
			$this->getLog()?->out(
				text: "ERROR! Ping already sent, waiting for response",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_WARN
			);
			$this->disconnect();
			return false;
		}
	}
	
	public function sendText(string $payload, int $max_frame_size = 0xFFFF): bool
	{
		$this->time_last_message = microtime(true);
		$this->ping_count = 0;
		return $this->sendMessage(WebSocketCommon\PROT_OPCODE_TEXT, false, $payload, $max_frame_size);
	}
	
	public function sendBinary(string $payload, int $max_frame_size = 0xFFFF): bool
	{
		$this->time_last_message = microtime(true);
		$this->ping_count = 0;
		return $this->sendMessage(WebSocketCommon\PROT_OPCODE_BINARY, false, $payload, $max_frame_size);
	}
	
	private function sendMessage($opcode, $masked, $payload, $max_frame_size = 0xFFFF): bool
	{
		$this->getLog()?->out(
			text: "Send Message (" . strtoupper(WebSocketCommon\PROT_OPCODE_DESCR[$opcode]) . ")",
			entity_id: $this->getID(),
			additional_data: $this->server->log_payloads ? $payload : null
		);
		if ($opcode === WebSocketCommon\PROT_OPCODE_CONTINUATION || $opcode === WebSocketCommon\PROT_OPCODE_RESERVED)
		{
			$this->setError(
				code: WebSocketCommon\ERROR_CLIENT_WRONG_OPCODE,
				entity_id: $this->getID()
			);
			return false;
		}
			
		$payload_size = strlen($payload);
			
		// fist determine if we need fragmentation
		if ($opcode === WebSocketCommon\PROT_OPCODE_TEXT || $opcode === WebSocketCommon\PROT_OPCODE_BINARY)
		{
			// calculate the overhead for every message
			$header_size = 2; // [FIN + RSV1 + RSV2 + RSV3 + OPCODE(4 bit)] + [MASK + PAYLOAD LEN(7 bit)] = 16 bit
			
			if ($masked)
				$header_size += 4; // 32 bit
			
			// calc the payload len overhead if it would be sent as a whole
			$full_payload_overhead = 0;
			if ($payload_size > 125 && $payload_size <= 0xFFFF) // 16 bit unsigned int
				$full_payload_overhead = 2;
			else if ($payload_size > 0xFFFF && $payload_size <= PHP_INT_MAX)  // 64 bit unsigned int
				$full_payload_overhead = 8;

			if ($header_size + $full_payload_overhead + $payload_size <= $max_frame_size) // do we really need it?
			{
				$need_fragmentation = false;
			}
			else
			{
				$need_fragmentation = true;
				
				if ($max_frame_size <= $header_size) // we need space for at least 1 content byte
					throw new \Exception("ERROR! max_frame_size too small");
				else if ($max_frame_size > 125 && $max_frame_size <= 0xFFFF) // 16 bit unsigned int
					$header_size += 2;
				else if ($max_frame_size > 0xFFFF && $max_frame_size <= PHP_INT_MAX)  // 64 bit unsigned int
					$header_size += 8;
				else
				{
					$this->setError(
						code: WebSocketCommon\ERROR_CLIENT_PAYLOAD_SIZE,
						exception: false,
						entity_id: $this->getID()
					);
					return false;
				}
			}
		}
		else
		{
			$need_fragmentation = false; // fragmentation is not allowed on control frames
			if ($payload_size > 125)
			{
				$this->setError(
					code: WebSocketCommon\ERROR_CLIENT_PAYLOAD_SIZE,
					exception: false,
					entity_id: $this->getID()
				);
				return false;
			}
		}
		
		if ($need_fragmentation)
		{
			$this->getLog()?->out(
				text: "Sending fragmented message",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
			
			$max_payload_data_per_frame = $max_frame_size - $header_size;
			$offset = 0;
			$first = true;
			$rc = true;
			
			while (true)
			{
				$tmp = substr($payload, $offset, $max_payload_data_per_frame);
				$offset += strlen($tmp);
				$last = $offset >= $payload_size;
				
				$this->getLog()?->out(
					text: "Sending frame, size " . strlen($tmp),
					entity_id: $this->getID(),
					level: WebSocketCommon\constLogLevels::LOG_LEVEL_TRACE
				);
				
				$rc = $rc && $this->sendFrame($first ? $opcode : WebSocketCommon\PROT_OPCODE_CONTINUATION, $masked, $tmp, $last, 0, 0, 0);
				if ($rc === false)
				{
					$this->getLog()?->out(
						text: "Breaking send",
						entity_id: $this->getID(),
						level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
					);
					return false;
				}
				if ($last)
				{
					$this->getLog()?->out(
						text: "Done",
						entity_id: $this->getID(),
						level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
					);
					break;
				}
				$first = false;
			}
			return true;
		}
		else
		{
			$this->getLog()?->out(
				text: "Sending single-frame message",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
			return $this->sendFrame($opcode, $masked, $payload, true, 0, 0, 0);
		}
	}
	
	private function sendFrame($opcode, $masked, $payload, $fin, $rsv1, $rsv2, $rsv3): bool
	{
		$this->resetError();
		try
		{
			$bytes = [];

			$byte = 0;

			if ($fin)	$byte |= 0x80;
			if ($rsv1)	$byte |= 0x40;
			if ($rsv2)	$byte |= 0x20;
			if ($rsv3)	$byte |= 0x10;

			if ($masked) $byte |= 0x08;

			$byte |= $opcode;
			$bytes[] = $byte;

			$byte = 0;

			if ($masked)	$byte |= 0x80;

			$payload_size = strlen($payload);
			if ($payload_size <= 125) // we need space for at least 1 content byte
			{
				$byte |= $payload_size;
				$bytes[] = $byte;
			}
			else if ($payload_size <= 0xFFFF) // 16 bit unsigned int
			{
				$byte |= 126;
				$bytes[] = $byte;

				$tmp = unpack("C*", pack("n", $payload_size));
				array_push($bytes, ...$tmp);
			}
			else if ($payload_size <= PHP_INT_MAX)  // 64 bit unsigned int
			{
				$byte |= 127;
				$bytes[] = $byte;

				$tmp = unpack("C*", pack("J", $payload_size));
				array_push($bytes, ...$tmp);
			}
			else
            {
                throw new \Exception("max supported frame size is " . PHP_INT_MAX);
            }

			if ($masked)
			{
				$mask = pack("N", random_int(0, 0xFFFFFFFF));

				$tmp = unpack("C*", $mask);
				array_push($bytes, ...$tmp);

				for ($i = 0; $i < $payload_size; $i++)
				{
					$bytes[] = $payload[$i] ^ $mask[$i % 4];
				}
				$frame = pack("C*", ...$bytes);
			}
			else
			{
				$frame = pack("C*", ...$bytes);
				$frame .= $payload;
			}

			$rc = @fwrite($this->sock, $frame, strlen($frame));
			fflush($this->sock);
			if ($rc === false)
			{
				throw new Common\NonCriticalException("Unable to send frame");
			}
			$this->time_last_snd = microtime(true);

			return true;
		}
		catch (Common\NonCriticalException $e)
		{
			$this->disconnect();
			$this->setError(code: WebSocketCommon\ERROR_SEND,
				additional_data: $e->getMessage(),
				exception: false,
				entity_id: $this->getID()
			);
			$this->client->onError(WebSocketCommon\ERROR_SEND, $e->getMessage());
			return false;
		}
	}
	
	/*
	 * returns:
	 *  the last payload if no receive is in progress
	 *  false if a reception is in progress
	 *  null if no payload is available
	 */
	public function getRcvPayload(): mixed
	{
		if ($this->buffer_read === null)
		{
			return $this->last_rcv_payload;
		}
		else
		{
			return false;
		}
	}
	
	public function getLastRcvPayload(): mixed
	{
		return $this->last_rcv_payload;
	}

	//TODO: manage the correct code/text values as defaults
	public function disconnect($code = -1, $text = "NOT_SET")
	{
		if ($this->sock === null)
			return;

		$this->client?->onClose($code, $text);
		
		@fclose($this->sock);
		$this->sock = null;

		$id = $this->getID();
        $this->client?->service->removeClient($id);
		$this->server->removeSocket($id);
		$this->server->removeClient($id);

		$this->getLog()?->out(
			text: "Web Client disconnected - IP: " . $this->getIP(),
			entity_id: $this->getID()
		);
	}
}
