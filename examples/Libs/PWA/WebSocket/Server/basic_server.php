<?php

use FF\Libs\PWA\WebSocket\Server\Client_base;
use FF\Libs\PWA\WebSocket\Server\Server;
use FF\Libs\PWA\WebSocket\Server\Service_base;
use FF\Libs\PWA\WebSocket\Server\Websocket;
use FF\Core\Sapi\Rule;

require "vendor/autoload.php";

class myClient extends Client_base {

	function onOpen(): bool
	{
		return true;
	}

	function onError($code, $text)
	{
	}

	function onClose($code, $text)
	{
	}

	function onMessage($type, $payload)
	{
	}

	function getInfo(): array|null
	{
		return null;
	}

	function send(mixed $data): bool
	{
		return $this->websocket->sendText($data);
	}
}


class myService extends Service_base
{
	public function onNewClient($client):bool {
		return true;
	}

	public function onRemoveClient($client) {
	}
}

$server = new Server(websocket_class: Websocket::class);

$server->addr = "0.0.0.0";
$server->port = "9100";

$server->allowed_hosts = [
	"www.ffphp.com:9100",
	"localhost:9100",
];

$server->allowed_origins = [
	"http://localhost",
	"http://www.ffphp.com",
];

$service = new myService(myClient::class);
$server->addService("the_only_service", $service);
$server->router->addRule((new Rule())
	->setSource(path: "/")
	->setDestination("service", "the_only_service")
);

$server->start();
