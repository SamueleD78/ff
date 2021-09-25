<?php

use FF\Core\Common\constLogLevels;
use FF\Core\Common\Log;
use FF\Core\Sapi\Rule;
use FF\Libs\PWA\WebSocket\Server\Authenticator_simple;
use FF\Libs\PWA\WebSocket\Server\Client_base;
use FF\Libs\PWA\WebSocket\Server\ControlClient_base;
use FF\Libs\PWA\WebSocket\Server\ControlInterface_unixsock;
use FF\Libs\PWA\WebSocket\Server\Server;
use FF\Libs\PWA\WebSocket\Server\Service_base;
use FF\Libs\PWA\WebSocket\Server\Websocket;
use JetBrains\PhpStorm\ArrayShape;

require "../vendor/autoload.php";

try {

	##############################################################
	# 1: SERVER AND SERVICE

	class myClient extends Client_base
	{

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
		public function onNewClient($client): bool
		{
			return true;
		}

		public function onRemoveClient($client)
		{
		}
	}

	$server = new Server(websocket_class: Websocket::class);

	$server->getLog()
		->setLevel(constLogLevels::LOG_LEVEL_DEBUG)
		->setOpt("to_file", true)
		->setOpt("path", "/my_log_dir/server.log")
		->setOpt("path_errors", "/my_log_dir/server-errors.log");

	$server->addr = "0.0.0.0";
	$server->port = "9100";

	$server->allowed_hosts = [
		"www.ffphp.com:9100",
		"localhost:9100",
	];

	$server->allowed_origins = [
		"https://localhost",
		"https://www.ffphp.com",
	];

	$service = new myService(myClient::class);
	$service->ping_interval_mins = 5;
	$service->ping_max_before_dc = 3;

	$server->addService("the_only_service", $service);
	$server->router->addRule((new Rule())
		->setSource(path: "/")
		->setDestination("service", "the_only_service")
	);

	$server->ssl = true;

	$server->ssl_options = [
		'cafile'            => "/my_certs_dir/your_ca.pem",
		'local_cert'        => "/my_certs_dir/your_domain.crt",
		'local_pk'          => "/my_certs_dir/your_domain.key",
		'allow_self_signed' => true,
		'verify_peer' 		=> false,
	];

	##############################################################
	# 2: CONTROL INTERFACE

	$server->log_control_if = "control_log";
	$server->log_control_clients = "control_log";
	Log::get("control_log")
		->setOpt("to_file", true)
		->setOpt("path", "/my_log_dir/control.log")
		->setOpt("path_errors", "/my_log_dir/control-errors.log");

	class myControlClient extends ControlClient_base
	{
		#[ArrayShape(["error" => "int"])] public function onCustomCmd($payload): array|false
		{
			return ["error" => 0];
		}
	}

	$control = new ControlInterface_unixsock(new Authenticator_simple(), myControlClient::class);
	$control->path = "/my_tmp_dir";
	$control->name = "server.sock";

	$control->encryption = true;
	$control->enc_private_key = "/my_keys_dir/key-priv.crt";

	$server->addControlIF($control);

	$server->start(daemonize: true);
} catch (Exception $exception) {
	exit($exception->getCode());
}

exit($server->getLastErrorCode());
