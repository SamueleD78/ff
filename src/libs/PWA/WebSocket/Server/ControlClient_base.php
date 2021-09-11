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
use FF\Core\Common;
use FF\Libs\PWA\WebSocket\Common\Log;
use JetBrains\PhpStorm\ArrayShape;

abstract class ControlClient_base
{
	use WebSocketCommon\Errors;
	
	public ?ControlInterface_base $interface = null;

	protected ?string $id = null;
	protected bool $authenticated = false;
	
	protected bool $first_message = true;
	
	protected ?Service_base $service = null;
	protected ?string $read_buffer = null;
	
	protected mixed $sock = null;

	#[ArrayShape([
		"secret" => "string",
		"algo" => "string",
		"services_allowed" => "array",
		"services_disallowed" => "array",
		"priority" => "int"
	])]
	public mixed $account = null;
	
	const COMMANDS_MAP = [
		WebSocketCommon\COMMAND_SELECT_SERVICE	=> "cmdSelectService",
		WebSocketCommon\COMMAND_LIST_CLIENTS		=> "cmdListClients",
		WebSocketCommon\COMMAND_SEND_MESSAGE		=> "cmdSendMessage",
	];

	#[ArrayShape(["error" => "int"])] abstract public function onCustomCmd($payload): array|false;

	public function __construct(ControlInterface_base $interface, $sock)
	{
		$this->interface = $interface;
		$this->sock = $sock;
	}

	public function getLog(): ?Log
	{
		return Log::get($this->interface->server->log_control_clients);
	}

	protected function send_command(int $command_type, array $arguments = []): bool
	{
		if ($command_type <= 0 || $command_type > 0xFFFF) // 16 bit
		{
			$this->setError(code: WebSocketCommon\ERROR_COMMAND_TYPE); //TODO check, maybe a throw instead?
			return false;
		}

		if (!isset($arguments["error"]))
			$arguments["error"] = 0;
		
		$tmp_encoded = \json_encode($arguments);
		if (\json_last_error())
		{
			$this->setError(
				code: WebSocketCommon\ERROR_COMMAND_PARAMETERS,
				additional_data: ["arguments" => var_export($arguments, true), "error" => \json_last_error_msg()],
				entity_id: $this->getID()
			);
			return false;
		}

		$this->getLog()?->out(
			text: "Sending CMD",
			entity_id: $this->getID(),
			additional_data: $this->interface->log_payloads ? $arguments : null,
		);

		$bytes = [];
		
		$tmp = unpack("C*", pack("n", $command_type));
		array_push($bytes, ...$tmp);
		
		$payload = pack("C*", ...$bytes);
		$payload .= $tmp_encoded;
		
		return $this->send_frame($payload, WebSocketCommon\FLAG_COMMAND);
	}
	
	protected function send_frame(string $payload, int $flags = 0): bool
	{
		$bytes = [];

		if ($this->interface->encryption)
		{
			$res = $this->interface->encrypt($payload);
			if ($res === false)
				return false;
			
			$payload = $res;
			$flags |= WebSocketCommon\FLAG_ENCRYPTED;
		}

		// control byte
		$bytes[] = $flags;

		$tmp = unpack("C*", pack("J", strlen($payload)));
		array_push($bytes, ...$tmp);

		$frame = pack("C*", ...$bytes);
		$frame .= $payload;

		$rc = @fwrite($this->sock, $frame);
		fflush($this->sock);

		if ($rc === false)
		{
			$this->setError(
				code: WebSocketCommon\ERROR_SEND,
				exception: false,
				entity_id: $this->getID()
			);
			return false;
		}
		
		return true;
	}
	
	public function receive($raw_data, $bytes): ?bool
	{
		$this->read_buffer .= $raw_data;
		
		while (true)
		{
			if (strlen($this->read_buffer) < 9) // 9 is the minimum length for a message: 1 byte control + 8 byte length
			{
				$this->getLog()?->out(
					text: "partial raw_data, buffer length " . strlen($this->read_buffer) . " bytes - Postponing..",
					entity_id: $this->getID(),
					level: WebSocketCommon\constLogLevels::LOG_LEVEL_TRACE
				);
				return null; // wait for the content to be full
			}

			$control_byte = unpack("cint8", $this->read_buffer[0])["int8"];
			
			$payload_length = substr($this->read_buffer, 1, 8);
			$read_buffer = substr($this->read_buffer, 9);

			$payload_length = unpack("Juint64", $payload_length)["uint64"];

			if (strlen($read_buffer) < $payload_length)
			{
				$this->getLog()?->out(
					text: "partial buffered data, expected " . strlen($this->read_buffer) . " bytes, got " . strlen($read_buffer) . " - Postponing..",
					entity_id: $this->getID(),
					level: WebSocketCommon\constLogLevels::LOG_LEVEL_TRACE
				);
				return null; // wait for the content to be full
			}

			$raw_payload = substr($read_buffer, 0, $payload_length); // get the payload
			$this->read_buffer = substr($read_buffer, $payload_length); // truncate the buffer for subsequent readings

			if ($control_byte || WebSocketCommon\FLAG_ENCRYPTED)
			{
				if (!$this->interface->encryption)
				{
					$this->setError(
						code: WebSocketCommon\ERROR_ENCRYPTED_UNEXPECTED,
						exception: false,
						entity_id: $this->getID()
					);
					return false;
				}
				
				$decrypted_payload = $this->interface->decrypt($raw_payload);
				if ($decrypted_payload === false)
				{
					$this->setError(
						code: WebSocketCommon\ERROR_DECRYPT_FAILED,
						exception: false,
						entity_id: $this->getID()
					);
					return false;
				}
			}
			else
			{
				if ($this->interface->encryption)
				{
					$this->setError(
						code: WebSocketCommon\ERROR_ENCRYPTED_EXPECTED,
						exception: false,
						entity_id: $this->getID()
					);
					return false;
				}
				
				$decrypted_payload = $raw_payload;
			}
			
			$command = null;
			if ($control_byte & WebSocketCommon\FLAG_COMMAND)
			{
				$command = unpack("nuint16", substr($decrypted_payload, 0, 2))["uint16"];
				$decrypted_payload = \json_decode(substr($decrypted_payload, 2), true);
				if (\json_last_error())
				{
					$this->setError(
						code: WebSocketCommon\ERROR_MESSAGE_FORMAT,
						exception: false,
						entity_id: $this->getID()
					);
					return false;
				}
			}
			
			$this->getLog()?->out(
				text: "message complete, Dispatching..",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);
			$rc = $this->dispatch($command, $decrypted_payload);
			if ($rc === false)
				return false;

			if (!strlen($this->read_buffer))
				break;
			
			$this->getLog()?->out(
				text: "some data left in the buffer, trying to process them",
				entity_id: $this->getID(),
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_TRACE);
		}
		
		return true;
	}
	
	public function disconnect()
	{
		if ($this->sock)
			@fclose($this->sock);

		$id = $this->getID();
		$this->interface->server->removeSocket($id);
		$this->interface->removeSocket($id);
		$this->interface->removeClient($id);

		$this->getLog()?->out(
			text: "disconnected control client",
			entity_id: $id
		);
	}
	
	public function cmdHelo(): bool
	{
		$this->getLog()?->out(
			text: "Sending CMD HELO",
			entity_id: $this->getID(),
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		
		return $this->send_command(WebSocketCommon\COMMAND_HELO, [
			"version" => WebSocketCommon\VERSION
		]);
	}
	
	public function isAuthenticated(): bool
	{
		return $this->authenticated;
	}

	public function setID($id)
	{
		$this->id = $id;
	}
	
	public function getID(): ?string
	{
		return $this->id;
	}
	
	protected function dispatch($command, $payload): bool
	{
		if ($this->first_message)
		{
			$this->getLog()?->out(
				text: "Received AUTH",
				entity_id: $this->getID(),
				additional_data: $this->interface->log_auth_payloads ? [$payload] : null,
			);

			$this->first_message = false;
			
			if ($command !== WebSocketCommon\COMMAND_AUTH)
			{
				$this->setError(
					code: WebSocketCommon\ERROR_CONTROL_PROTOCOL,
					additional_data: "expected COMMAND_AUTH",
					exception: false
				);
				return false;
			}
			
			$this->authenticated = $this->interface->authenticator->authenticate($payload, $this);
			$this->send_command(WebSocketCommon\COMMAND_AUTH, ["auth" => $this->authenticated]);
			
			return $this->authenticated;
		}
		else if ($command)
		{
			if ($command < 1000)
			{
				$this->getLog()?->out(
					text: "Received SYSTEM CMD",
					entity_id: $this->getID(),
					additional_data: $this->interface->log_payloads ? ["cmd" => $command, "payload" => $payload] : null,
				);

				return $this->onSystemCommand($command, $payload);
			}
			else
			{
				$this->getLog()?->out(
					text: "Received CMD",
					entity_id: $this->getID(),
					additional_data: $this->interface->log_payloads ? ["cmd" => $command, "payload" => $payload] : null,
				);

				return $this->onCommand($command, $payload);
			}
		}
		else
		{
			$this->getLog()?->out(
				text: "Received CUSTOM CMD",
				entity_id: $this->getID(),
				additional_data: $this->interface->log_payloads ? ["cmd" => $command, "payload" => $payload] : null,
			);

			$rc = $this->onCustomCmd($payload);
			if ($rc === false)
			{
				return false;
			}
			else
			{
				$this->send_command(WebSocketCommon\COMMAND_CUSTOM_CMD, $rc);
				return true;
			}
		}
	}
	
	protected function getContainer(): Server_base|Service_base
	{
		if ($this->service === null)
		{
			if ($this->interface->authenticator->authorizeService(null, $this))
			{
				return $this->interface->server;
			}
			else
			{
				throw new Common\NonCriticalException(__FUNCTION__, WebSocketCommon\ERROR_UNAUTHORIZED);
			}
		}
		else
		{
			return $this->service;
		}
	}

	//TODO: gestire i system command come ping/pong
	public function onSystemCommand(int $command, array $params): bool
	{
		return true;
	}


	public function onCommand(int $command, array $params): bool
	{
		if (isset(self::COMMANDS_MAP[$command]))
		{
			try
			{
				$rc = call_user_func_array([$this, self::COMMANDS_MAP[$command]], ["params" => $params]);
				if ($rc === false)
					return false;
				
				$rc = ["result" => $rc];
				
				return $this->send_command($command, $rc);
			}
			catch (Common\NonCriticalException $e)
			{
				$this->setError(
					code: $e->getCode(),
					exception: false,
					entity_id: $this->getID()
				);
				return $this->send_command($command, ["error" => $e->getCode()]);
			}
		}
		else
		{
			$this->setError(
				code: WebSocketCommon\ERROR_CONTROL_COMMAND_UNKNOWN,
				exception: false,
				entity_id: $this->getID()
			);
			return false;
		}
	}

	protected function cmdSelectService($params)
	{
		$service = null;
		$service_name = null;

		if (isset($params["path"]))
		{
			$matched_rules = $this->interface->server->router->process($params["path"]);
			foreach ($matched_rules as $match)
			{
				$service_name = $match["rule"]->destination->service;
				break;
			}
		}
		else if(isset($params["name"]))
		{
			$service_name = $params["name"];
		}
		else
		{
			throw new Common\NonCriticalException(__FUNCTION__, WebSocketCommon\ERROR_MISSING_PARAM);
		}

		if ($service_name !== null)
		{
			$service = $this->interface->server->getService($service_name);
		}

		if ($service === null || $service === false)
		{
			throw new Common\NonCriticalException(__FUNCTION__, WebSocketCommon\ERROR_SERVICE_NOT_FOUND);
		}

		$rc = $this->interface->authenticator->authorizeService($service_name, $this);
		if (!$rc)
		{
			throw new Common\NonCriticalException(__FUNCTION__, WebSocketCommon\ERROR_UNAUTHORIZED);
		}

		$this->service = $service;
		return $service_name;
	}

	function cmdListClients($params): array
	{
		$container = $this->getContainer();
		
		$out = [];
		foreach ($container->getClients() as $id => $client)
		{
			$out[$id] = $client->getInfo();
		}
		
		return $out;
	}

	function cmdSendMessage($params): bool|array
	{
		$container = $this->getContainer();
		$clients = $container->getClients();
		
		if (is_string($params["recipients"])) // single one
		{
			$id = $params["recipients"];
			if (!isset($clients[$id]))
				throw new Common\NonCriticalException(__FUNCTION__, WebSocketCommon\ERROR_UNKNOWN_CLIENT);

			return $clients[$id]->send($params["message"]);
		}
		else if (is_array($params["recipients"])) // multi send
		{
			return $container->sendTo($params["recipients"], $params["message"]);
		}
		else
		{
			return false;
		}
	}
}
