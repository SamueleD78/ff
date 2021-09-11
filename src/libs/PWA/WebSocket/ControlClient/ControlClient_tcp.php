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
use FF\Libs\PWA\WebSocket\Common\Log;
use JetBrains\PhpStorm\ArrayShape;

class ControlClient_tcp extends ControlClient_base
{
	public string 	$addr			= "127.0.0.1";
	public int 		$port			= 9000;

	public bool 	$ssl			= false;

	#[ArrayShape([
		"peer_name" => "string",
		"verify_peer" => "bool",
		"verify_peer_name" => "bool",
		"allow_self_signed" => "bool",
		"cafile" => "string",
		"capath" => "string",
		"local_cert" => "string",
		"local_pk" => "string",
		"passphrase" => "string",
		"verify_depth" => "int",
		"ciphers" => "string",
		"capture_peer_cert" => "bool",
		"capture_peer_cert_chain" => "bool",
		"SNI_enabled" => "bool",
		"disable_compression" => "bool",
		"peer_fingerprint" => "string | array",
		"security_level" => "int",
	])]
	public array $ssl_options		= []; // see https://www.php.net/manual/en/context.ssl.php

	public string $log_key = "cc";

	public function getLog(): ?Log
	{
		return Log::get($this->log_key);
	}

	public function connect(string $path) : bool
	{
		$this->disconnect(true);
		
		$errno = null;
		$errstr = null;

		if ($this->ssl)
		{
			WebSocketCommon\DebugOutput(text: "Create a streaming socket of type TCP/IP over SSL on address " . $this->addr . ":" . $this->port . ".. ", level: 0, newline: false);

			$context = stream_context_create([
				"ssl" => $this->ssl_options
			]);
			if (!is_resource($context))
			{
				$this->setError(code: WebSocketCommon\ERROR_CONTEXT_CREATION);
				return false;
			}

			$rc = stream_socket_client(
				"ssl://" . $this->addr . ":" . $this->port,
				$errno,
				$errstr,
				$this->connect_timeout,
				STREAM_CLIENT_CONNECT,
				$context
			);
		}
		else
		{
			WebSocketCommon\DebugOutput(text: "Create a streaming socket of type TCP/IP on address " . $this->addr . ":" . $this->port . ".. ", level: 0, newline: false);
			$rc = stream_socket_server(
				"tcp://" . $this->addr . ":" . $this->port,
				$errno,
				$errstr,
				$this->connect_timeout,
				STREAM_CLIENT_CONNECT
			);
		}

		if ($rc === false)
		{
			$this->setError(WebSocketCommon\ERROR_CONNECT, [
				"errno"		=> $errno,
				"errstr"	=> $errstr,
			]);
			return false;
		}
		
		$this->sock = $rc;
		stream_set_blocking($this->sock, true);

		$helo = $this->receive($this->connect_timeout);
		if ($helo === false || $helo["type"] !== WebSocketCommon\COMMAND_HELO)
		{
			$this->disconnect(true);
			$this->setError(WebSocketCommon\ERROR_SERVER_ERROR);
			return false;
		}
		
		if (version_compare($helo["payload"]["version"], WebSocketCommon\VERSION, ">"))
		{
			$this->disconnect(true);
			$this->setError(WebSocketCommon\ERROR_PROTOCOL_TOO_NEW);
			return false;
		}
		
		return true;
	}

    #[ArrayShape(["type" => "int", "payload" => "array"])]  protected function receive(int $timeout = 1): null|bool|array
	{
		if (!$this->isConnected())
		{
			$this->setError(WebSocketCommon\ERROR_NOT_CONNECTED);
			return false;
		}
		
		$write = null;
		$except = null;
		
		$this->buffer_read = "";

		$timer_start = time();
		while (true)
		{
			$read = [
				$this->sock
			];
			
			$rc = @stream_select($read, $write, $except, 0, 200000);
			if (function_exists("pcntl_signal_dispatch")) /* for CLI execution */
				\pcntl_signal_dispatch();

			if ($rc === false)
			    return false;
			
			if (count($read))
			{
				foreach ($read as $sock)
				{


					$raw_data = "";
					$bytes_to_read = WebSocketCommon\RCV_BUFFER_SIZE;
					while (true) {
						$rc = fread($sock, $bytes_to_read);
						if ($rc === false)
						{
							$raw_data = false;
							break;
						}
						$raw_data .= $rc;
						$status = socket_get_status ($sock); // https://stackoverflow.com/a/7501565/3747858

						if (isset($status["unread_bytes"]) && $status["unread_bytes"])
							$bytes_to_read = $status["unread_bytes"];
						else
							break;
					}
					$bytes = strlen($raw_data);

					if ($raw_data === false)
					{
						$this->disconnect(true);
						$this->setError(WebSocketCommon\ERROR_DISCONNECTED);
						return false;
					}
					
					$this->buffer_read .= $raw_data;
					
					$res = $this->read_frame();
					if ($res === false)
						return false;
					else if ($res !== null)
						break 2; // there is only 1 sock reading, no need to check other socks
				}
			}
			
			if (time() - $timer_start >= $timeout)
			{
				$this->setError(WebSocketCommon\ERROR_RESPONSE_TIMEOUT);
				return false;
			}
		}
		
		return $res;
	}
}
