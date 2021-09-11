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

abstract class ControlInterface_unixsock extends ControlInterface_base
{
	public ?string 		$path 		= null; // default to sys_get_temp_dir()
	public string 		$name 		= "ffwebsocket";
	public int			$mode		= 0777;
	
	private ?string 	$fullpath 	= null;

	protected function getSockPath(): string
	{
		return $this->path ?? sys_get_temp_dir();
	}

	public function start(): bool|null
	{
		$this->getLog()?->trace();

		$rc = parent::start();
		if ($rc === false)
			return false;

		if ($this->sock !== null)
			return null;

		$this->getLog()?->out(
			text: "creating streaming unix socket.. ",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
			newline: false
		);
		
		$errno = null;
		$errstr = null;
		
		$this->fullpath = $this->getSockPath() . DIRECTORY_SEPARATOR . $this->name;
		
		if (file_exists($this->fullpath))
		{
			$this->setError(code: WebSocketCommon\ERROR_UNIXSOCK_EXISTS,
				additional_data: $this->fullpath,
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
			return false;
		}

		$this->sock = stream_socket_server(
			"unix://" . $this->fullpath,
			$errno,
			$errstr,
			STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
		);
		
		if ($this->sock === false)
		{
			$this->setError(code: WebSocketCommon\ERROR_CONTROL_SOCKET,
				additional_data: ["fullpath" => $this->fullpath, "errno" => $errno, "errstr" => $errstr],
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
			return false;
		}

		@chmod($this->fullpath, $this->mode);
		
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
		$this->getLog()?->trace();

		if ($this->sock === null)
			return;

		$this->getLog()?->out(
			text: "Stopping.. ",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
			newline: false
		);

		@fclose($this->sock);
		$this->sock = null;

		@unlink($this->fullpath);

		$this->getLog()?->out(
			text: "Done",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
	}

	public function accept(): ?bool
	{
		$this->getLog()?->trace();

		$this->getLog()?->out(
			text: "Accepting new connection.. ",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
			newline: false
		);

		$newsock = @stream_socket_accept($this->sock);
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
			text: "Done [" . $id . "]",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);

		return $newclient->cmdHelo(); // required by the v1.0.0 control protocol version
	}

	public function receive(string $id, mixed $sock): void
	{
		$this->getLog()?->trace();

		$raw_data = "";
		$bytes_to_read = WebSocketCommon\RCV_BUFFER_SIZE;
		while (true) {
			$raw_data .= fread($sock, $bytes_to_read);
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
		if (!strlen($raw_data) && feof($sock)) // unix sockets don't respond with false on fread
		{
			$this->getLog()?->out(
				text: "FEOF, disconnecting",
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
