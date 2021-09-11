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

abstract class ControlInterface_base
{
	use Clients, WebSocketCommon\Errors;
	
	private ?string 		$id;
	
	public ?Server_base 	$server = null;
	
	public ?string 			$controlclient_class = null;

	public bool				$log_payloads 		= true;
	public bool				$log_auth_payloads 	= false;

	/**
	 * apply an encryption to all the contents received, except for the first 9 byte (control byte + len byte)
	 * @var bool
	 */
	public bool 													$encryption 		= false; // if ture, encrypt/decrypt data with RSA
	public \OpenSSLAsymmetricKey|\OpenSSLCertificate|string|null 	$enc_private_key 	= null;
	public string 													$enc_passphrase		= "";
	
	private \OpenSSLAsymmetricKey|false|null 						$private_key		= null;
	private ?int 													$max_enc_size		= 117;

	public ?Authenticator_base 										$authenticator 		= null;

	protected mixed 	$sock 		= null;

	/**
	 * @var array<string, mixed>
	 */
	protected array 	$sockets 	= [];

	abstract public function stop(): void;
	abstract public function accept(): ?bool;
	abstract public function receive(string $id, mixed $sock): void;

	public function __construct(Authenticator_base $authenticator, $controlclient_class)
	{
		$this->authenticator = $authenticator;
		$this->controlclient_class = $controlclient_class;
	}

	public function setServer(Server_base $server)
	{
		if ($this->server)
			throw new \Exception("The instance already belong to a server");
		$this->server = $server;
	}
	
	public function getLog(): ?Log
	{
		return Log::get($this->server->log_control_if);
	}

	public function setID($id)
	{
		$this->id = $id;
	}

	public function getID(): ?string
	{
		return $this->id;
	}

	public function removeSocket($sock_id)
	{
		$this->getLog()?->trace();
		unset($this->sockets[$sock_id]);
	}

	public function decrypt($raw_data): bool|string
	{
		$this->getLog()?->trace();

		$this->getLog()?->out(
			text: "Decrypting data.. ",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
			newline: false
		);

		$offset = 0;
		$buffer = "";
		while ($offset < strlen($raw_data))
		{
			if (strlen($raw_data) < $offset + 2)
			{
				$this->setError(code: WebSocketCommon\ERROR_ENCRYPT_FAILED, level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG);
				return false;
			}
			$length = unpack("nuint16", substr($raw_data, $offset, 2))["uint16"];

			if (strlen($raw_data) < $offset + 2 + $length)
			{
				$this->setError(code: WebSocketCommon\ERROR_ENCRYPT_FAILED);
				return false;
			}
			$slice = substr($raw_data, $offset + 2, $length);
			$offset += 2 + $length;

			$rc = openssl_private_decrypt($slice, $decrypted, $this->private_key, OPENSSL_PKCS1_PADDING);
			if ($rc === false)
			{
				$this->setError(code: WebSocketCommon\ERROR_DECRYPT_FAILED, exception: false);
				return false;
			}

			$buffer .= $decrypted;
		}

		$this->getLog()?->out(
			text: "Done",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		return $buffer;
	}
	
	public function encrypt($raw_data): bool|string
	{
		$this->getLog()?->trace();
		$this->getLog()?->out(
			text: "Encrypting data.. ",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG,
			newline: false
		);

		$offset = 0;
		$buffer = "";
		while ($offset < strlen($raw_data))
		{
			$slice = substr($raw_data, $offset, $this->max_enc_size);
			$offset += strlen($slice);

			$rc = openssl_private_encrypt($slice, $crypted, $this->private_key, OPENSSL_PKCS1_PADDING);
			if ($rc === false)
			{
				$this->setError(code: WebSocketCommon\ERROR_ENCRYPT_FAILED);
				return false;
			}

			$buffer .= pack("C*", ...unpack("C*", pack("n", strlen($crypted))));
			$buffer .= $crypted;
		}

		$this->getLog()?->out(
			text: "Done",
			level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
		);
		return $buffer;
	}

	public function start(): bool|null
	{
		$this->getLog()?->trace();
		if ($this->encryption)
		{
			$this->getLog()?->out(
				text: "Initializing encryption data",
				level: WebSocketCommon\constLogLevels::LOG_LEVEL_DEBUG
			);

			if (is_string($this->enc_private_key))
			{
				$rc = openssl_pkey_get_private("file://" . $this->enc_private_key, $this->enc_passphrase);
				if ($rc === false)
				{
					$this->setError(code: WebSocketCommon\ERROR_ENCRYPT_WRONGKEY, additional_data: $this->enc_private_key);
					return false;
				}

				if (($dtl = openssl_pkey_get_details($rc)) && isset($dtl["bits"]))
				{
					$this->max_enc_size = $dtl["bits"]/8 - 11; // https://www.php.net/manual/en/function.openssl-private-encrypt.php
				}

				$this->private_key = $rc;
			}
			else if (is_resource($this->enc_private_key))
			{
				$this->private_key = $this->enc_private_key;
			}
			else
			{
				$this->setError(code: WebSocketCommon\ERROR_ENCRYPT_WRONGKEY, additional_data: var_export($this->enc_private_key, true));
				return false;
			}
		}

		return true;
	}
}
