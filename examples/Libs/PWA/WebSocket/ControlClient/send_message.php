<?php

use FF\Libs\PWA\WebSocket\ControlClient\ControlClient_unixsock;

require "../vendor/autoload.php";

$conn = new ControlClient_unixsock();

$rc = $conn->setEncryption(
	enable: true,
	publickey: "file://" . dirname(__DIR__) . "/keys/data_public.crt"
);
if (!$rc)
	throw new \Exception(
		message: "Unable to set encryption: " . $conn->getLastErrorString(),
		code: $conn->getLastErrorCode()
	);

$rc = $conn->auth([
	"id"        => "admin",
	"secret"    => "password" /* yes, super safe! */
]);
if (!$rc)
	throw new \Exception(
		message: "Unable to auth: " . $conn->getLastErrorString(),
		code: $conn->getLastErrorCode()
	);

$conn->selectServiceByName("the_only_service");
if (!$rc)
	throw new \Exception(
		message: "Unable to select the service: " . $conn->getLastErrorString(),
		code: $conn->getLastErrorCode()
	);

$clients = $conn->listClients();
if ($clients === false)
	throw new \Exception(
		message: "Unable to retrieve client list: " . $conn->getLastErrorString(),
		code: $conn->getLastErrorCode()
	);

if (is_array($clients))
{
	$rc = $conn->sendMessage("I can see you...", array_keys($clients));
	if ($rc === false)
		throw new \Exception(
			message: "Unable to send message to clients: " . $conn->getLastErrorString(),
			code: $conn->getLastErrorCode()
		);
	else
		var_dump($rc); // display a report on the send
}

$conn->disconnect();
