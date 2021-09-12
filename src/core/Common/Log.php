<?php
/**
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link http://www.formsphpframework.com
 */

namespace FF\Core\Common;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;

class Log
{
	/**
	 * @var array <string, Log>
	 */
	private static array $entities = [];

	private static array $streams 	= [
		STREAM_STDOUT => [
			"stream" 			=> STDOUT,
			"last_newline" 		=> true,
			"last_was_error" 	=> false
		],
		STREAM_STDERR => [
			"stream" 			=> STDERR,
			"last_newline" 		=> true,
			"last_was_error" 	=> false
		],

	]; // used to share the streams between Entities

	private string $name; // it's possible to enter %id for the split function, see below

	private int $level = constLogLevels::LOG_LEVEL_INFO;

	public bool $log_name 			= false; // useful when the same log stream is shared between different entities
	public bool $to_file 			= false;
	public bool $file_append 		= true;
	public bool $separate_errors	= true;
	public bool $file_split_by_id	= false;
	public ?string $path			= null;			// it's possible to enter %id for the split function, see below
	public ?string $path_errors		= null;	// it's possible to enter %id for the split function, see below

	public string $default_stream 			= STREAM_STDOUT;
	public string $default_stream_errors 	= STREAM_STDERR;

	private int $class_name_padding = 25;

	private function __construct(string $name)
	{
		$this->name = $name;
	}

	public function getLevel(): int
	{
		return $this->level;
	}

	public function setLevel(
		#[ExpectedValues(valuesFromClass: constLogLevels::class)]
		int $level
	): self
	{
		$this->level = $level;
		return $this;
	}

	public static function get(?string $name): ?Log
	{
		if ($name === null)
			return null;

		if (!isset(self::$entities[$name]))
		{
			self::$entities[$name] = new self($name);
		}

		return self::$entities[$name];
	}

	public function setOpt(
		#[ExpectedValues([
			"level",
			"log_name",
			"to_file",
			"file_append",
			"separate_errors",
			"file_split_by_id",
			"path",
			"path_errors",
			"default_stream",
			"default_stream_errors",
			"class_name_padding",
		])]
		string $name,
		mixed $value): self
	{
		$this->$name = $value;
		return $this;
	}

	#[ArrayShape(["stream" => "resource", "last_newline" => "bool", "last_was_error" => "bool"])]
	public function &getStream(null|int|string $id, bool $is_error = false): array
	{
		$key = $this->getStreamKey(id: $id, is_error: $is_error);

		if (!isset(self::$streams[$key]))
		{
			self::$streams[$key] = [
				"stream" 			=> null,
				"last_newline" 		=> true,
				"last_was_error" 	=> false
			];
			if (false === self::$streams[$key]["stream"] = fopen($key, $this->file_append ? "a" : "w"))
				throw new \Exception("Unable to open/create log file " . $key);
		}

		return self::$streams[$key];
	}

	private function getStreamKey(null|int|string $id, bool $is_error): string
	{
		if (!$this->to_file)
		{
			if (!$is_error || !$this->separate_errors)
				return $this->default_stream;
			else
				return $this->default_stream_errors;
		}

		$file = (!$is_error || !$this->separate_errors) ? $this->path : $this->path_errors;

		if (!strlen($file))
			throw new \Exception("path is required when logging to file is selected");

		return "file://" . str_replace("%id", $id, $file);
	}

	public function out(
		string $text,
		null|int|string $entity_id = null,
		mixed $additional_data = null,
		#[ExpectedValues(valuesFromClass: constLogLevels::class)]
		int $level = constLogLevels::LOG_LEVEL_INFO,
		bool $newline = true,
		int $indent = 0,
		bool $class_name = true,
		bool $force = false,
	): void
	{
		if ($level > $this->level && !$force)
			return;

		$is_error = (false !== array_search($level, [
				/*LOG_LEVEL_WARN,*/
				constLogLevels::LOG_LEVEL_ERROR,
				constLogLevels::LOG_LEVEL_FATAL,
			]));

		$stream =& $this->getStream(id: $entity_id, is_error: $is_error);

		// if is error, breaks old appended values
		if ($is_error && !$stream["last_newline"] && !$stream["last_was_error"])
		{
			$infostream =& $this->getStream(id: $entity_id,is_error: false);
			fwrite($infostream["stream"], "ERROR\n");
			$infostream["last_newline"] = true;
		}

		$output = "";
		if ($stream["last_newline"])
		{
			$date = \DateTime::createFromFormat('U.u', sprintf("%.6F", microtime(true))); // https://github.com/philkra/elastic-apm-php-agent/issues/3
			$output .= $date->format('Y-m-d H:i:s.u') . " ";

			$output .= str_pad(constLogLevels::LOG_LEVEL_DESCR[$level], 5) . " ";

			if ($this->log_name)
				$output .= $this->name . " ";

			if ($class_name)
			{
				$dbg_bcktrc = debug_backtrace();
				for ($i =  1; $i < count($dbg_bcktrc); $i++) {
					$data = $dbg_bcktrc[$i];
					if (isset($data["object"]))
						$tmp = get_class($data["object"]);
					else
						$tmp = $data["class"] ?? "";
					if (strlen($tmp)) {
						$tmp = explode("\\", $tmp);
						$tmp = array_pop($tmp);
						if ($tmp === "Log")
							continue;

						$output .= str_pad($tmp, $this->class_name_padding) . " ";
						break;
					}
				}
			}

			if (!$this->file_split_by_id)
				$output .= $entity_id !== null ? "[" . $entity_id . "] " : "";

			if ($indent)
				$output .= str_repeat("\t", $indent);
		}

		$output .= $text;

		if ($additional_data !== null)
			$output .= " >> " . preg_replace("/^\\s*|\\n/m", "", var_export($additional_data, true));

		$output .= ($newline ? "\n" : "");

		fwrite($stream["stream"], $output);

		$stream["last_newline"] = $newline;
	}

	function error(int $code,
				   string $string,
				   mixed $additional_data = null,
						 #[ExpectedValues(valuesFromClass: constLogLevels::class)]
				   int $level = constLogLevels::LOG_LEVEL_ERROR,
				   null|bool|string $entity_id = null
	): void
	{
		$output = "";

		$output .= ($code > 0 ? "(" . $code . ") " : "");
		$output .= $string;
		//$output .= ($additional_data !== null ? " " . var_export($additional_data, true) : "");

		$this->out(text: $output, entity_id: $entity_id, additional_data: $additional_data, level: $level);
	}

	function trace(): void
	{
		$data = debug_backtrace()[1];

		$text = "";

		if ($data["type"])
			$text .= $data["class"] . $data["type"];

		$text .= $data["function"] . (strlen($data["function"]) ? "() " : "");

		if (!strlen($text))
			$text .= $data["file"] . ":" . $data["line"];

		$this->out(text: $text, level: constLogLevels::LOG_LEVEL_TRACE,class_name: false);
	}
}
