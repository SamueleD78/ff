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

use FF\Libs\PWA\WebSocket\Common\Log;
use FF\Core\Sapi;
use FF\Core\Common;
use JetBrains\PhpStorm\ArrayShape;

abstract class Server_base
{
	use Clients, WebSocketCommon\Errors;

	public string 	$addr			= "127.0.0.1";
	public int 		$port			= 9000;
	/**
	 * @var string[]
	 */
	public array $allowed_hosts	= [];
	/**
	 * @var string[]
	 */
	public array $allowed_origins	= [];
	
	public bool $ssl				= false;
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

	protected mixed 	$sock			= null;
	protected ?string 	$sock_id		= null;

	/**
	 * @var array
	 */
	protected array $sockets			= [];
	protected array $reverse_sockets	= [];
	protected array $sockets_bt_type	= [];

	/**
	 * @var ControlInterface_base[]
	 */
	protected array $control_interfaces = [];
	
	public ?Sapi\Router $router = null;

	/**
	 * @var array<string, Service_base>
	 */
	protected array $services = [];

	private ?string $websocket_class;

	public ?string 	$log_server					= "websocket_server";
	public ?string 	$log_services				= "websocket_server";
	public ?string 	$log_sockets				= "websocket_server";
	public ?string 	$log_clients				= "websocket_server";
	public ?string 	$log_control_if				= "websocket_server";
	public ?string 	$log_control_clients		= "websocket_server";

	public bool		$log_payloads				= true;

	abstract function onReady();
	abstract function onBeforeStop();
	abstract function onStop();

	function __construct(string $websocket_class)
	{
		$this->router = new Sapi\Router();
		$this->websocket_class = $websocket_class;
	}

	function getLog(): ?Log
	{
		return Log::get($this->log_server);
	}

	public function addControlIF(ControlInterface_base $instance): void
	{
		$instance->setServer($this);
		$this->control_interfaces[] = $instance;
	}

	public function addService(string $name, Service_base $instance): void
	{
		if (isset($this->services[$name]))
			throw new \Exception ("a Service with the same name already exists");
		else
		{
			$instance->setServer($this);
			$this->services[$name] = $instance;
		}
	}

	public function getService($name): Service_base|bool
	{
		return $this->services[$name] ?? false;
	}

	public function addSocket(mixed $sock, Server_base|Websocket_base|ControlInterface_base|ControlClient_base $obj, int $type): string
	{
		$id = Common\uuidv4();

		$this->sockets[$id] = $sock;

		if (!isset($this->sockets_bt_type[$type]))
			$this->sockets_bt_type[$type] = [];
		$this->sockets_bt_type[$type][$id] = $sock;

		$this->reverse_sockets[$id] = [
			"type" => $type,
			"obj" => $obj
		];
		return $id;
	}

	public function removeSocket($id)
	{
		$tmp = $this->reverse_sockets[$id];
		unset($this->sockets[$id]);
		unset($this->sockets_bt_type[$tmp["type"]][$id]);
		unset($this->reverse_sockets[$id]);
	}

	public function route(Websocket_base $websocket, $url, $query): Client_base|false
	{
		$this->getLog()?->trace();

		$matched_rules = $this->router->process($url, $query);
		foreach ($matched_rules as $match)
		{
			$service = $match["rule"]->destination->service;

			$this->getLog()?->out(
				text: "Attaching new client to service " . $service,
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
			
			$tmp = $this->services[$service]->newClient($websocket, $match);
			$this->addClient($websocket->getID(), $tmp);
			return $tmp;
		}
		
		return false;
	}

	public function start($daemonize = false): bool|null
	{
		if ($daemonize)
		{
			$this->getLog()->out("Forms PHP Framework WebSocket Server v" . WebSocketCommon\VERSION);
			$this->getLog()->out("Copyright (c) 2021, Samuele Diella <samuele.diella@gmail.com>");
		}
		else
		{
			fwrite(STDOUT, "Forms PHP Framework WebSocket Server v" . WebSocketCommon\VERSION . "\n");
			fwrite(STDOUT, "Copyright (c) 2021, Samuele Diella <samuele.diella@gmail.com>\n\n");
		}

		$this->getLog()?->trace();

		if ($this->sock !== null)
		{
			$this->setError(code: WebSocketCommon\ERROR_ALREADY_STARTED, level: WebSocketCommon\constLogLevels::LOG_LEVEL_WARN);
			return null;
		}

		$this->getLog()?->out(
			text: "Starting server.."
		);

		$this->getLog()?->out(
			text: "Checking router rules.. ",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
			newline: false
		);
		foreach ($this->router->rules as $priority)
		{
			foreach ($priority as $rule)
			{
				if (!isset($rule->destination->service))
				{
					$this->setError(code: WebSocketCommon\ERROR_SERVICE_MISSING_DEST,level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
					return false;
				}

				$service = $rule->destination->service;
				if (!isset($this->services[$service]))
				{
					$this->setError(code: WebSocketCommon\ERROR_SERVICE_MISSING_DEST,level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
					return false;
				}
			}
		}
		$this->getLog()?->out(
			text: "OK",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);

		$this->clients = [];

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
				$this->setError(code: WebSocketCommon\ERROR_CONTEXT_CREATION, level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
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

		if (!$this->sock)
		{
			$this->setError(code: WebSocketCommon\ERROR_SERVER_SOCKET, additional_data: ["errno" => $errno, "errstr" => $errstr],level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
			return false;
		}
		stream_set_blocking($this->sock, false);
		$this->sock_id = $this->addSocket($this->sock, $this, WebSocketCommon\SOCK_TYPE_SERVER);

		// Signal management (sadly SIGKILL could not be managed)
		pcntl_signal(SIGTERM, function ($signal) {
			$this->getLog()?->out(
				text: "caught SIGTERM",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_WARN
			);
			$this->stop();
			exit;
		});
		pcntl_signal(SIGINT, function ($signal) { // debugger stop
			$this->getLog()?->out(
				text: "caught SIGINT",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_WARN
			);
			$this->stop();
			exit;
		});
		/*pcntl_signal(SIGHUP, function ($signal) { // restart
		});
		pcntl_signal(SIGUSR1, function ($signal) { // user
		});*/

		$this->getLog()?->out(
			text: "Done",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);

		$this->getLog()?->out(
			text: "Starting all the control interfaces..",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		if (count($this->control_interfaces))
		{
			$this->getLog()?->out(
				text: "Starting control sockets..",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);

			foreach ($this->control_interfaces as $tmp)
			{
				$rc = $tmp->start(); // the interface will add the socket internally
				if ($rc === false)
				{
					$this->stop();
					$this->setError(code: WebSocketCommon\ERROR_CONTROL_INTERFACE, additional_data: $tmp->getLastError(),level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
					return false;
				}
			}
		}
		$this->getLog()?->out(
			text: "Done",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);

		$this->onReady();

		$this->getLog()?->out(
			text: "Listening"
		);

		while (true)
		{
			// create a copy, so the originals don't get modified by stream_select()
			$read = $this->sockets;
			$write = null;
			$except = null;

			// get a list of all the sockets that have data to be read from
			// if there are no sockets with data, go to next iteration
			$rc = @stream_select($read, $write, $except, 0, 200000);

			pcntl_signal_dispatch();

			if ($rc === false)
			{
				$this->setError(code: WebSocketCommon\ERROR_SERVER_STREAM_SELECT,level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL);
				$this->stop();
				return false;
			}
			else if ($rc !== 0) {
				// determine the proper manager for the action

				// 1: control intefaces, check if there is a control client trying to connect
				if (isset($this->sockets_bt_type[WebSocketCommon\SOCK_TYPE_CONTROL_INTERFACE])) {
					$control_sockets = array_intersect(array_keys($this->sockets_bt_type[WebSocketCommon\SOCK_TYPE_CONTROL_INTERFACE]), array_keys($read));
					if (count($control_sockets)) {
						foreach ($control_sockets as $sock_id) {
							unset($read[$sock_id]);
							/* @var $tmp_obj ControlInterface_base */
							$tmp_obj = $this->reverse_sockets[$sock_id]["obj"];

							$this->getLog()?->out(
								text: "Connection on control socket " . get_class($tmp_obj),
								level: WebSocketCommon\constLogLevels::LOG_LEVEL_FATAL
							);
							$tmp_obj->accept(); // will add the socket internally
						}
					}
				}

				// 2: control clients
				if (isset($this->sockets_bt_type[WebSocketCommon\SOCK_TYPE_CONTROL_CLIENT])) {
					$control_clients = array_intersect(array_keys($this->sockets_bt_type[WebSocketCommon\SOCK_TYPE_CONTROL_CLIENT]), array_keys($read));
					if (count($control_clients)) {
						foreach ($control_clients as $sock_id) {
							unset($read[$sock_id]);
							/* @var $tmp_obj ControlClient_base */
							$tmp_obj = $this->reverse_sockets[$sock_id]["obj"];

							$this->getLog()?->out(
								text: "Read on control socket " . get_class($tmp_obj) . " [" . $sock_id . "]",
								level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
							);
							$tmp_obj->interface->receive($sock_id, $this->sockets[$sock_id]); // the socket is passed to empty it
						}
					}
				}

				// 3: web server, check if there is a web client trying to connect
				if (isset($read[$this->sock_id])) {
					try {
						// remove the listening socket from the clients-with-data array to avoid iteration in the next cycle
						unset($read[$this->sock_id]);

						$this->getLog()?->out(
							text: "Accepting new connection.. ",
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
							newline: false
						);

						if ($this->ssl) stream_set_blocking($this->sock, true); // https://www.php.net/manual/en/function.stream-socket-server.php#118419 point number 3
						$newsock = @stream_socket_accept($this->sock);
						if ($this->ssl) stream_set_blocking($this->sock, false);

						if ($newsock === false) {
							$this->setError(code: WebSocketCommon\ERROR_SERVER_ACCEPT, exception: false);
							throw new Common\NonCriticalException();
						}

						stream_set_blocking($this->sock, false);

						$this->getLog()?->out(
							text: "Done",
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
						);

						/* all good, create the Websocket intermediate object
							the client will be created after the handshake because it depends on the service routing */

						/* @var $newclient_sock Websocket_base */
						$newclient_sock = new $this->websocket_class($newsock, $this);
						$tmp_id = $this->addSocket($newsock, $newclient_sock, WebSocketCommon\SOCK_TYPE_CLIENT);
						$newclient_sock->setID($tmp_id);

						$this->getLog()?->out(
							text: "New web client connected, sock [" . $newclient_sock->getID() . "] - IP: " . $newclient_sock->getIP()
						);
					} catch (Common\NonCriticalException $ex) {
						// do nothing
					}
				}

				// 4: web clients
				foreach ($read as $sock_id => $read_sock) {
					$this->getLog()?->out(
						text: "Reading data on sock [" . $sock_id . "].. ",
						level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
						newline: false
					);

					$raw_data = "";
					$bytes_to_read = WebSocketCommon\RCV_BUFFER_SIZE;
					while (true) {
						$rc = fread($read_sock, $bytes_to_read);
						if ($rc === false) {
							$raw_data = false;
							break;
						}
						$raw_data .= $rc;
						$status = socket_get_status($read_sock); // https://stackoverflow.com/a/7501565/3747858

						if (isset($status["unread_bytes"]) && $status["unread_bytes"])
							$bytes_to_read = $status["unread_bytes"];
						else
							break;
					}
					$bytes = strlen($raw_data);

					if (!isset($this->reverse_sockets[$sock_id])) {
						$this->setError(code: WebSocketCommon\ERROR_UNKNOWN_CLIENT);
						$this->getLog()?->out(
							text: "detaching web socket [" . $sock_id . "]",
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
						);
						@fclose($read_sock);
						$this->removeSocket($sock_id);
						continue;
					}

					/** @var Websocket_base $websocket */
					$websocket = $this->reverse_sockets[$sock_id]["obj"];
					$websocket->time_last_rcv = microtime(true);

					// check if the web client is disconnected
					if ($raw_data === false) {
						$this->getLog()?->out(
							text: "FALSE on fread, disconnecting",
							level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
						);

						$websocket->disconnect();

						// continue to the next client to read from, if any
						continue;
					}

					if (!$bytes) {
						break;
					}

					$this->getLog()?->out(
						text: "Received " . $bytes . " bytes",
						level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
					);

					$rc = $websocket->receive($raw_data, $bytes); // if a client is started, it will dispatch everything to the client
					//TODO: manage the correct action based on $rc
					if ($rc === false) // error
					{
					} else if ($rc === null) // ignore
					{
					} else if ($rc === true) // got something
					{
					}
				} // end of reading foreach
			}

			// check for periodic events

			// first, services
			foreach ($this->services as $name => $service)
			{
				$service->serverTick();
			}
		}
	}

	public function stop()
	{
		$this->getLog()?->trace();

		if ($this->sock === null)
			return;

		$this->getLog()?->out(
			text: "Initiating shutdown sequence.."
		);

		$this->onBeforeStop();

		// 1: stop clients
		$this->getLog()?->out(
			text: "disconnecting clients..",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		if (isset($this->sockets_bt_type[WebSocketCommon\SOCK_TYPE_CLIENT]))
		{
			foreach ($this->sockets_bt_type[WebSocketCommon\SOCK_TYPE_CLIENT] as $id => $sock)
			{
				$websocket = $this->reverse_sockets[$id]["obj"];
				$websocket->disconnect();
			}
		}

		// 2: stop this server
		$this->getLog()?->out(
			text: "Stopping server..",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		@fclose($this->sock);
		$this->sock = null;

		// 3: stop control interfaces (it will disconnect all the control clients connected)
		$this->getLog()?->out(
			text: "Stopping Control Interfaces..",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		foreach ($this->control_interfaces as $control)
		{
			$control->stop();
		}

		$this->onStop();

		$this->getLog()?->out(
			text: "Done"
		);
	}
}
