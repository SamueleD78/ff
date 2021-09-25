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
use FF\Core\Common\Log;
use FF\Core\Sapi\MatchedRule;
use FF\Libs\PWA\WebSocket\Common as WebSocketCommon;

abstract class Service_base
{
	use Clients {
		removeClient as protected Clients_removeClient; // this method will be overridden to fire an event
	}
	
	private ?string $client_type;
	public ?Server $server = null;

	public ?int $ping_interval_mins				= null;
	public ?int $ping_max_before_dc				= null;
	public string $ping_payload 				= "knock knock";

	abstract function onNewClient($client): bool;
	abstract function onRemoveClient(Client_base $client);

	function __construct(string $client_type)
	{
		$this->client_type = $client_type;
	}

	function getLog(): ?Log
	{
		return Log::get($this->server->log_services);
	}

	public function setServer(Server $server): void
	{
		if ($this->server)
			throw new \Exception("The instance already belong to a server");

		$this->server = $server;
	}

	public function newClient(Websocket $websocket, MatchedRule $router_match): Client_base|false
	{
		$this->getLog()?->out(
			text: "Attaching client [" . $websocket->getID() . "]"
		);

		/* @var $tmp Client_base */
		$tmp = new $this->client_type(websocket: $websocket, router_match: $router_match, service: $this);
		
		$rc = $this->onNewClient($tmp);
		if ($rc === false)
			return false;

		$id = $websocket->getID();
		$this->addClient($id, $tmp);

		return $tmp;
	}
	
	public function removeClient($id)
	{
		$this->getLog()?->out(
			text: "Detaching client [" . $id . "]"
		);

        $this->onRemoveClient($this->clients[$id]);
		$this->Clients_removeClient($id);
	}

	public function serverTick()
	{
		if ($this->ping_interval_mins !== null)
		{
			foreach ($this->clients as $id => $client)
			{
				if ($client->websocket->ping_pending) // ping already in progress
					continue;

				$time_last = $client->websocket->time_last_message ?? $client->websocket->time_connected;

				$last_message = WebSocketCommon\microtime_details(microtime(true) - $time_last);

				if ($last_message["s"] < $this->ping_interval_mins)
					continue;

				if ($client->websocket->sent_ping_time !== null)
				{
					$last_ping = WebSocketCommon\microtime_details(microtime(true) - $client->websocket->sent_ping_time);
					if ($last_ping["s"] < $this->ping_interval_mins)
						continue;
				}

				if ($this->ping_max_before_dc !== null && $client->websocket->ping_count === $this->ping_max_before_dc)
				{
					$client->websocket->disconnect(WebSocketCommon\ERROR_CLIENT_INACTIVE);
					continue;
				}

				$client->websocket->sendPing($this->ping_payload);
			}
		}
	}
}
