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
use FF\Libs\PWA\WebSocket\Common;
use JetBrains\PhpStorm\ArrayShape;

trait Clients
{
	/**
	 * @var array<string, Client_base|ControlClient>
	 */
	protected array $clients = [];

	/**
	 * @return Client_base[]|ControlClient[]
	 */
	public function getClients(): array
	{
		return $this->clients;
	}

	public function addClient($id, Client_base|ControlClient $client): void
	{
		$this->clients[$id] = $client;
	}

	public function removeClient($id): void
	{
		unset($this->clients[$id]);
	}

	/*
	 * $exclude must be a list of clients id (socket resource id)
	 */
	#[ArrayShape(["errors" => "array", "sent" => "array"])] public function sendToAll($data, $exclude = []): array
	{
		$tmp = array_keys($this->clients);
		$tmp = array_diff($tmp, $exclude);
		return $this->sendTo($tmp, $data);
	}

	/*
	 * $exclude must be a list of clients id (socket resource id)
	 */
	#[ArrayShape(["errors" => "array", "sent" => "array"])] public function sendTo(array $clients, $data): array
	{
		$results = [
			"errors"    => [],
			"sent"      => [],
		];

		foreach ($clients as $client)
		{
			if (is_string($client) || is_int($client))
			{
				$client_id = $client;
				if ($client = ($this->clients[$client] ?? null))
				{
					$rc = $client->send($data);
					if ($rc === false)
					{
						$results["errors"][$client_id] = [
							"code"	=> Common\ERROR_SEND,
							"descr"	=> "socket error",
							"data"	=> $client->websocket->getLastError()
						];
					}
					else
					{
						$results["sent"][$client_id] = [
							"code"	=> 0,
						];
					}
				}
				else
				{
					$results["errors"][$client_id] = [
						"code" => Common\ERROR_UNKNOWN_CLIENT,
						"descr" => "Unhandled client handler: " . var_export($client_id, true)
					];
				}
			}
			else if (is_subclass_of($client, "FF\Libs\PWA\WebSocket\Server\Client_base"))
			{
				$rc = $client->send($data);
				if ($rc === false)
				{
					$results["errors"][$client->websocket->getID()] = [
						"code"	=> Common\ERROR_SEND,
						"descr"	=> "socket error",
						"data"	=> $client->websocket->getLastError()
					];
				}
				else
				{
					$results["sent"][$client->websocket->getID()] = [
						"code"	=> 0,
					];
				}
			}
			else
			{
				$results["errors"][$client] = [
					"code"	=> Common\ERROR_WRONG_HANDLER,
					"descr"	=> "Unhandled client handler: " . var_export($client, true)
				];
			}
		}

		return $results;
	}
}