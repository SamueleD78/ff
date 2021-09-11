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

use FF\Libs\PWA\WebSocket\Common\Log;

abstract class Client_base
{
	public Websocket|null  $websocket 		= null;
	public Service_base|null    $service       	= null;
	protected ?array            $router_match 	= null;

	abstract function onOpen(): bool;
	abstract function onError($code, $text);
	abstract function onClose($code, $text);
	abstract function onMessage($type, $payload);
	abstract function getInfo(): array | null;
	
	abstract function send(mixed $data): bool;
	
	public function __construct(Websocket $websocket, $router_match, Service_base $service)
	{
		$this->websocket = $websocket;
		$this->service = $service;
		$this->router_match = $router_match;
	}

	public function getLog() : Log
	{
		return Log::get($this->service->server->log_clients);
	}
}
