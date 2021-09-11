<?php
namespace FF\Libs\PWA\WebSocket\Server;

use Exception;
use FF\Libs\PWA\WebSocket\Common;

class Authenticator_simple extends Authenticator_base
{
	const ALGO_PLAIN 			= "";

	const PRIORITY_ALLOWED 		= 1;
	const PRIORITY_DISALLOWED 	= 2;

	protected array $accounts = [
			"admin" => [
				"secret" 	=> "password",
				"algo" 		=> "string",
				"services_allowed"		=> [
					"*"
				],
				"services_disallowed"	=> [
				],
				"priority" => self::PRIORITY_ALLOWED
			]
		];

	public function addAccount(string $id, string $secret, string $algo, array $services_allowed = ["*"], array $services_disallowed = ["*"], int $priority = self::PRIORITY_ALLOWED): bool
	{
		if (isset($this->accounts[$id]))
			return false;

		if (array_search($algo, hash_algos()) === false)
			throw new Exception("Unsupported Algo");

		$this->accounts[$id] = [
			"secret" 				=> $secret,
			"algo" 					=> $algo,
			"services_allowed" 		=> $services_allowed,
			"services_disallowed" 	=> $services_disallowed,
			"priority"				=> $priority
		];

		return true;
	}

	public function changeAccount(string $id,
								  string|null $secret = null,
								  string|null $algo = null,
								  array|null $services_allowed = null,
								  array|null $services_disallowed = null,
								  int|null $priority = null): bool
	{
		if (!isset($this->accounts[$id]))
			return false;

		if ($secret !== null)
			$this->accounts[$id]["secret"] = $secret;

		if ($algo !== null)
		{
			if (array_search($algo, hash_algos()) === false)
				throw new Exception("Unsupported Algo");

			$this->accounts[$id]["algo"] = $algo;
		}

		if ($services_allowed !== null)
			$this->accounts[$id]["services_allowed"] = $services_allowed;

		if ($services_disallowed !== null)
			$this->accounts[$id]["services_disallowed"] = $services_disallowed;

		if ($priority !== null)
			$this->accounts[$id]["priority"] = $priority;

		return true;
	}

	public function delAccount(string $id): bool
	{
		if (isset($this->accounts[$id]))
		{
			unset($this->accounts[$id]);
			return true;
		}
		else
		{
			return false;
		}
	}

	public function authenticate(array $payload, ControlClient_base $client):bool {
		if (!isset($payload["id"]))
		{
			$client->setError(
				code: Common\ERROR_UNAUTHORIZED,
				entity_id: $client->getID()
			);
			$client->disconnect();
		}

		if (!isset($this->accounts[$payload["id"]]))
		{
			$client->setError(
				code: Common\ERROR_UNAUTHORIZED,
				entity_id: $client->getID()
			);
			$client->disconnect();
		}

		$tmp = $this->accounts[$payload["id"]];
		$secret = $payload["secret"];

		if ($tmp["algo"] !== self::ALGO_PLAIN)
		{
			$secret = hash($tmp["algo"], $secret, false);
		}

		if ($secret === $tmp["secret"])
		{
			$client->getLog()?->out(
				text: "Authenticated",
				entity_id: $client->getID(),
				//level: Common\constLogLevels::LOG_LEVEL_DEBUG
			);
			return true;
		}
		else
		{
			$client->setError(
				code: Common\ERROR_UNAUTHORIZED,
				additional_data: $payload,
				entity_id: $client->getID()
			);
			$client->getLog()?->out(
				text: "Auth failed",
				entity_id: $client->getID(),
				level: Common\constLogLevels::LOG_LEVEL_WARN
			);
			return false;
		}
	}

	public function authorizeService(string|null $service_name, ControlClient_base $client):bool {
		if ($client->account === null)
		{
			throw new Exception("Cannot authorize before auth");
		}

		$authorized = false;

		if ($client->account["priority"] === self::PRIORITY_ALLOWED)
		{
			$authorized = array_search("*", $client->account["services_allowed"]) !== false;
		}
		else
		{
			if (array_search("*", $client->account["services_allowed"]) === false)
				$authorized = array_search("*", $client->account["services_allowed"]) !== false;
		}

		if ($service_name === null) {
			if ($authorized)
				$client->getLog()?->out(
					text: "Authorizing on all the services",
					entity_id: $client->getID(),
					//level: Common\constLogLevels::LOG_LEVEL_DEBUG
				);
			else
				$client->getLog()?->out(
					text: "Denying auth on all the services",
					entity_id: $client->getID(),
					level: Common\constLogLevels::LOG_LEVEL_WARN
				);
			return $authorized;
		}

		if ($client->account["priority"] === self::PRIORITY_ALLOWED)
		{
			$rc = array_search($service_name, $client->account["services_allowed"]) !== false;
			if ($rc) // specific auth, go with that
			{
				$authorized = true;
			}
			else if ($authorized) // generic auth, search for specific disallow
			{
				$rc = array_search($service_name, $client->account["services_disallowed"]) !== false;
				if ($rc)
					$authorized = false;
			}
		}
		else
		{
			$rc = array_search($service_name, $client->account["services_disallowed"]) !== false;
			if ($rc) // specific auth, go with that
			{
				$authorized = false;
			}
			else if (!$authorized) // generic auth, search for specific allow
			{
				$authorized = array_search($service_name, $client->account["services_allowed"]) !== false;
			}
		}

		if ($authorized)
			$client->getLog()?->out(
				text: "Authorizing Client on service " . $service_name,
				entity_id: $client->getID(),
				//level: Common\constLogLevels::LOG_LEVEL_DEBUG
			);
		else
			$client->getLog()?->out(
				text: "Denying auth on service " . $service_name,
				entity_id: $client->getID(),
				level: Common\constLogLevels::LOG_LEVEL_WARN
			);
		return $authorized;
	}
}
