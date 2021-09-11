<?php
/**
 * WebSocket Server_base Library
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
use JetBrains\PhpStorm\ArrayShape;

class ControlInterface_tcp extends ControlInterface_base
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

	public function start(): bool|null
	{
		$rc = parent::start();
		if ($rc === false)
			return false;

		if ($this->sock !== null)
			return null;

		$errno = null;
		$errstr = null;

		if ($this->ssl)
		{
			$this->getLog()?->out(
				text: "Create a streaming socket of type TCP/IP over SSL on address " . $this->addr . ":" . $this->port . ".. ",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
				newline: false
			);

			$context = stream_context_create([
				"ssl" => $this->ssl_options
			]);
			if (!is_resource($context))
			{
				$this->setError(code: WebSocketCommon\ERROR_CONTEXT_CREATION,level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
				return false;
			}

			$this->sock = stream_socket_server(
				"ssl://" . $this->addr . ":" . $this->port,
				$errno,
				$errstr,
				STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
				$context
			);
		}
		else
		{
			$this->getLog()?->out(
				text: "Create a streaming socket of type TCP/IP on address " . $this->addr . ":" . $this->port . ".. ",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
				newline: false
			);
			$this->sock = stream_socket_server(
				"tcp://" . $this->addr . ":" . $this->port,
				$errno,
				$errstr,
				STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
			);
		}

		if ($this->sock === false)
		{
			$this->setError(code: WebSocketCommon\ERROR_CONTROL_SOCKET,
				additional_data: ["errno" => $errno, "errstr" => $errstr],
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
			return false;
		}

		stream_set_blocking($this->sock, false);
		$tmp_id = $this->server->addSocket($this->sock, $this, WebSocketCommon\SOCK_TYPE_CONTROL_INTERFACE);
		$this->setID($tmp_id);

		$this->getLog()?->out(
			text: "Done",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);

		return true;
	}

	public function stop(): void
	{
		if ($this->sock === null)
			return;

		$this->getLog()?->out(
			text: "Stopping.. ",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
			newline: false
		);

		@fclose($this->sock);
		$this->sock = null;

		$this->getLog()?->out(
			text: "Done",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
	}

	public function accept(): ?bool
	{
		$this->getLog()?->out(
			text: "Accepting new connection.. ",
			newline: false
		);

		if ($this->ssl)  stream_set_blocking($this->sock, true); // https://www.php.net/manual/en/function.stream-socket-server.php#118419 point number 3
		$newsock = @stream_socket_accept($this->sock);
		if ($this->ssl)  stream_set_blocking($this->sock, false);

		if ($newsock === false)
		{
			$this->setError(code: WebSocketCommon\ERROR_CONTROL_ACCEPT, exception: false);
			return null;
		}

		@stream_set_blocking($newsock, false);

		/* @var $newclient ControlClient_base */
		$newclient = new $this->controlclient_class($this, $newsock);
		$id = $this->server->addSocket($newsock, $newclient, WebSocketCommon\SOCK_TYPE_CONTROL_CLIENT);
		$newclient->setID($id);

		$this->sockets[$id] = $newsock;
		$this->addClient($id, $newclient);

		$this->getLog()?->out(
			text: "Done [" . $id . "]"
		);

		return $newclient->cmdHelo(); // required by the v1.0.0 control protocol version
	}

	public function receive(string $id, mixed $sock): void
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

		if (!isset($this->sockets[$id]))
		{
			$this->setError(code: WebSocketCommon\ERROR_UNKNOWN_SOCKET);
			$this->getLog()?->out(
				text: "Detaching socket [" . $id . "]",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_WARN
			);
			@fclose($sock);
			$this->server->removeSocket($id);
			return;
		}

		if (!isset($this->clients[$id]))
		{
			$this->setError(code: WebSocketCommon\ERROR_UNKNOWN_CLIENT);
			$this->getLog()?->out(
				text: "Detaching socket [" . $id . "]",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_WARN
			);
			@fclose($sock);
			$this->server->removeSocket($id);
			$this->removeSocket($id);
			return;
		}

		$client = $this->clients[$id];

		// check if the client is disconnected
		if ($raw_data === false)
		{
			$this->getLog()?->out(
				text: "FALSE on fread, disconnecting",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);

			$client->disconnect();
			return;
		}

		if (!$bytes)
		{
			return;
		}

		$this->getLog()?->out(
			text: "Data Received - client [" . $id . "] - " . $bytes . " bytes",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		
		$rc = $client->receive($raw_data, $bytes);
		if ($rc === false)
		{
			$client->disconnect();
			return;
		}
	}
}
