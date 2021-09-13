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

namespace FF\Libs\PWA\WebSocket\ControlClient;
use FF\Libs\PWA\WebSocket\Common as WebSocketCommon;
use FF\Core\Common;
use JetBrains\PhpStorm\ArrayShape;

abstract class ControlClient_base
{
	use Common\Errors;

	public ?string $log_key = null;

	public function getLog(): ?Common\Log
	{
		return Common\Log::get($this->log_key);
	}

	protected mixed $sock		= null;
	protected bool $encrypt	    = false;
	
	protected ?\OpenSSLAsymmetricKey $enc_public_key	= null;
	protected ?int $enc_padding		                    = null;
	private ?int 										$max_enc_size		= 117;

	protected int $commands_timeout			= 5; // in seconds
	protected int $connect_timeout			= 5; // in seconds
	
	protected string $buffer_read				= "";
	protected ?string $service					= null;

	public function getErrorString(int $code): string
	{
		return WebSocketCommon\get_error_string($code);
	}

	public function setCommandTimeout(int $secs)
	{
		if ($secs < 0)
			throw new \Exception("Wrong timeout value");
		
		$this->commands_timeout = $secs;
	}
	
	public function setConnectTimeout(int $secs)
	{
		if ($secs < 0)
			throw new \Exception("Wrong timeout value");
		
		$this->connect_timeout = $secs;
	}
	
	public function setEncryption(bool $enable = false, \OpenSSLAsymmetricKey|\OpenSSLCertificate|string $publickey = null, int $padding = OPENSSL_PKCS1_PADDING): bool
	{
		$this->encrypt = false;
		
		if ($enable)
		{
			if ($padding !== null && !in_array($padding, [0, OPENSSL_PKCS1_PADDING, OPENSSL_SSLV23_PADDING, OPENSSL_PKCS1_OAEP_PADDING, OPENSSL_NO_PADDING]))
			{
				throw new \Exception("Wrong Encryption Padding, can be one of OPENSSL_PKCS1_PADDING, OPENSSL_SSLV23_PADDING, OPENSSL_PKCS1_OAEP_PADDING, OPENSSL_NO_PADDING");
			}
			
			$rc = openssl_pkey_get_public($publickey);
			if ($rc === false)
			{
				$this->setError(WebSocketCommon\ERROR_ENCRYPT_WRONGKEY);
				return false;
			}

			if (($dtl = openssl_pkey_get_details($rc)) && isset($dtl["bits"]))
			{
				$this->max_enc_size = $dtl["bits"]/8 - 11; // https://www.php.net/manual/en/function.openssl-private-encrypt.php
			}
				
			$this->enc_public_key 	= $rc;
			$this->enc_padding 		= $padding;
			$this->encrypt 			= $enable;
		}
		
		return true;
	}

	public function decrypt($raw_data): bool|string
	{
		$offset = 0;
		$buffer = "";
		while ($offset < strlen($raw_data))
		{
			if (strlen($raw_data) < $offset + 2)
			{
				$this->setError(code: WebSocketCommon\ERROR_ENCRYPT_FAILED);
				return false;
			}
			$length = unpack("nuint16", substr($raw_data, $offset, 2))["uint16"];

			if (strlen($raw_data) < $offset + 2 + $length)
			{
				$this->setError(code: WebSocketCommon\ERROR_ENCRYPT_FAILED);
				return false;
			}
			$slice = substr($raw_data, $offset + 2, $length);
			$offset += 2 + $length;

			$rc = openssl_public_decrypt($slice, $decrypted, $this->enc_public_key, OPENSSL_PKCS1_PADDING);
			if ($rc === false)
			{
				$this->setError(code: WebSocketCommon\ERROR_DECRYPT_FAILED, exception: false);
				return false;
			}

			$buffer .= $decrypted;
		}

		return $buffer;
	}

	public function encrypt($raw_data): bool|string
	{
		$offset = 0;
		$buffer = "";
		while ($offset < strlen($raw_data))
		{
			$slice = substr($raw_data, $offset, $this->max_enc_size);
			$offset += strlen($slice);

			$rc = openssl_public_encrypt($slice, $crypted, $this->enc_public_key, OPENSSL_PKCS1_PADDING);
			if ($rc === false)
			{
				$this->setError(code: WebSocketCommon\ERROR_ENCRYPT_FAILED);
				return false;
			}

			$buffer .= pack("C*", ...unpack("C*", pack("n", strlen($crypted))));
			$buffer .= $crypted;
		}

		return $buffer;
	}

	public function sendCustomCmd(string $payload)
	{
		$this->resetError();
		
		$res = $this->send_frame($payload);
		if ($res["type"] !== WebSocketCommon\COMMAND_CUSTOM_CMD)
		{
			$this->disconnect(true);
			$this->setError(WebSocketCommon\ERROR_SERVER_ERROR);
			return false;
		}
		
		return $res["payload"];
	}
	
	protected function send_command(int $command_type, array $arguments = [])
	{
		$this->resetError();
		
		if ($command_type <= 0 || $command_type > 0xFFFF) // 16 bit
		{
			$this->setError(code: WebSocketCommon\ERROR_COMMAND_TYPE);
			return false;
		}
		
		$tmp_encoded = \json_encode($arguments);
		if (\json_last_error())
		{
			$this->setError(code: WebSocketCommon\ERROR_COMMAND_PARAMETERS, additional_data: ["arguments" => var_export($arguments, true), "error" => \json_last_error_msg()]);
			return false;
		}
		
		$bytes = [];
		
		$tmp = unpack("C*", pack("n", $command_type));
		array_push($bytes, ...$tmp);
		
		$payload = pack("C*", ...$bytes);
		$payload .= $tmp_encoded;
		
		$res = $this->send_frame($payload, WebSocketCommon\FLAG_COMMAND);
		if ($res === false)
			return false;
		
		if ($res["type"] !== $command_type)
		{
			$this->setError(WebSocketCommon\ERROR_MISMATCHED_ANSWER, [$command_type]);
			return false;
		}
		
		if ($res["payload"]["error"])
		{
			$this->setError($res["payload"]["error"]);
			return false;
		}
		
		return $res["payload"];
	}
	
	protected function send_frame(string $payload, int $flags = 0) : array|false
	{
		if (!$this->isConnected())
		{
			$this->setError(WebSocketCommon\ERROR_NOT_CONNECTED);
			return false;
		}
		
		$bytes = [];

		if ($this->encrypt)
		{
			$rc = $this->encrypt($payload);
			if ($rc === false)
				return false; // set error called inside encrypt()

			$payload = $rc;
			$flags |= WebSocketCommon\FLAG_ENCRYPTED;
		}

		// control byte
		$bytes[] = $flags;

		$tmp = unpack("C*", pack("J", strlen($payload)));
		array_push($bytes, ...$tmp);

		$frame = pack("C*", ...$bytes);
		$frame .= $payload;

		$rc = fwrite($this->sock, $frame);
		fflush($this->sock);
		
		if ($rc === false)
		{
			$this->setError(WebSocketCommon\ERROR_SEND);
			return false;
		}
		
		return $this->receive($this->commands_timeout);
	}

    #[ArrayShape(["type" => "int", "payload" => "array"])] function read_frame(): array|bool|null
	{
		if (strlen($this->buffer_read) < 9)
			return null;
		
		$control_byte = $this->buffer_read[0];
		$payload_length = substr($this->buffer_read, 1, 8);
		$buffer_read = substr($this->buffer_read, 9);

		$payload_length = unpack("Juint64", $payload_length)["uint64"];

		if (strlen($buffer_read) < $payload_length)
			return null;
			
		$payload = substr($buffer_read, 0, $payload_length); // get the payload
		$this->buffer_read = substr($buffer_read, $payload_length); // truncate the buffer for subsequent readings

		if ($this->encrypt)
		{
			if (!($control_byte && WebSocketCommon\FLAG_ENCRYPTED))
			{
				$this->setError(WebSocketCommon\ERROR_ENCRYPTED_EXPECTED);
				return false;
			}

			$rc = $this->decrypt($payload);
			if ($rc === false)
				return false; // setError called inside decrypt()

			$payload = $rc;
		}
		else
		{
			if ($control_byte && WebSocketCommon\FLAG_ENCRYPTED)
			{
				$this->setError(WebSocketCommon\ERROR_ENCRYPTED_UNEXPECTED);
				return false;
			}
		}
		
		$response_type = unpack("nuint16", substr($payload, 0, 2))["uint16"];
		$payload = \json_decode(substr($payload, 2), true);
		
		if (json_last_error())
		{
			$this->setError(WebSocketCommon\ERROR_SERVER_ERROR);
			return false;
		}
		
		if ($response_type < 1000)
		{
			$rc = $this->process_system_message($response_type, $payload);
			if ($rc === false)
				return false;
			
			if (strlen($this->buffer_read))
				return $this->read_frame();
			else
				return null;
		}
		else
		{
			return [
				"type"		=> $response_type,
				"payload"	=> $payload,
			];
		}
	}
	
	function process_system_message($type, $payload): bool
	{
		switch ($type)
		{
			default:
				$this->setError(WebSocketCommon\ERROR_UNKNOWN_SYS_MSG);
				return false;
		}
	}
	
	public function isConnected() : bool
	{
		if ($this->sock === null)
			return false;
		else
			return true;
	}

	public function disconnect($ignore_error = false) : bool
	{
		if (!$this->isConnected())
		{
			if (!$ignore_error)
			{
				$this->setError(WebSocketCommon\ERROR_NOT_CONNECTED);
				return false;
			}
		}
		else
		{
			@fclose($this->sock);
			$this->sock = null;
		}
			
		return true;
	}
	
	public function auth(array $params = [])
	{
		/*if (!strlen($name))
			throw new \Exception("you need to provide an authenticator name");*/
		
		$ret = $this->send_command(WebSocketCommon\COMMAND_AUTH, $params);
		
		if ($ret === false)
			return false;
		
		return $ret["auth"];
	}
	
	public function selectServiceByPath(string $path)
	{
		$url_parts = parse_url($path);
		if ($url_parts === false || isset($url_parts["host"]))
		{
			$this->setError(WebSocketCommon\ERROR_SERVICE_PATH, [$path]);
			return false;
		}
		
		$ret = $this->send_command(WebSocketCommon\COMMAND_SELECT_SERVICE, [
			"path" => $url_parts["path"],
		]);
		
		if ($ret === false)
			return false;
		
		$this->service = $ret["result"];
		return $this->service;
	}
	
	public function selectServiceByName(string $name)
	{
		if (!strlen($name))
			throw new \Exception("service name is required");
		
		$ret = $this->send_command(WebSocketCommon\COMMAND_SELECT_SERVICE, [
			"name" => $name,
		]);
		
		if ($ret === false)
			return false;
		
		$this->service = $ret["result"];
		return $this->service;
	}
	
	public function listClients()
	{
		$ret = $this->send_command(WebSocketCommon\COMMAND_LIST_CLIENTS);
		
		if ($ret === false)
			return false;
		
		return $ret["result"];
	}
	
	public function sendMessage(string $message, array|string $recipients)
	{
		$ret = $this->send_command(WebSocketCommon\COMMAND_SEND_MESSAGE, ["message" => $message, "recipients" => $recipients]);
		
		if ($ret === false)
			return false;
		
		return $ret["result"];
	}
}
