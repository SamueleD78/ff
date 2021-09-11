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

namespace FF\Core\Common;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;

trait Errors
{
	protected bool $raise_exceptions		= false;
	
	protected ?int $error_code				= null;
	protected ?string $error_string			= null;
	protected mixed $error_additional_data	= null;

	abstract public function getLog(): ?Log;
	abstract public function getErrorString(int $code): string;
	
	public function setRaiseExceptions(bool $enable)
	{
		$this->raise_exceptions = $enable;
	}
	
	#[ArrayShape(["code" => "int|null", "string" => "null|string", "data" => "mixed|null"])] public function getLastError(): array
	{
		return [
			"code"		=> $this->error_code,
			"string"	=> $this->error_string,
			"data"		=> $this->error_additional_data,
		];
	}
	
	public function getLastErrorCode(): int
	{
		return $this->error_code;
	}
	
	public function getLastErrorString(): string
	{
		return $this->error_string;
	}
	
	public function getLastErrorData(): mixed
	{
		return $this->error_additional_data;
	}
	
	protected function resetError()
	{
		$this->error_code = null;
		$this->error_string = null;
		$this->error_additional_data = null;
	}

	public function setError(int $code,
								mixed $additional_data = null,
								null|bool $exception = null,
								#[ExpectedValues(valuesFromClass: constLogLevels::class)]
								int $level = constLogLevels::LOG_LEVEL_ERROR,
								null|int|string $entity_id = null
	): void
	{
		$this->error_code				= $code;
		$this->error_string				= $this->getErrorString($code);
		$this->error_additional_data 	= $additional_data;

		$this->getLog()?->error(
			code: $this->error_code,
			string: $this->error_string,
			additional_data: $additional_data,
			level: $level,
			entity_id: $entity_id);

		if ($exception === true || ($exception === null && $this->raise_exceptions))
			throw new \Exception ($this->error_string, $this->error_code);
	}
}
