<?php
/**
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://www.ffphp.com
 */

namespace FF\Core\Common;

use JetBrains\PhpStorm\ExpectedValues;
use const FF\SAPI;

/**
 * This constant defines which errors will be filtered through the handler.
 * If you want to exclude the system errors, set it to E_ALL ^ ERRORS_SYSTEM
 */
if (!defined("FF\Core\Common\ERRORS_HANDLED"))					define("FF\Core\Common\ERRORS_HANDLED", E_ALL);

/**
 * This abstract class groups all error handling methods, providing mechanisms to log into the system logs, to dump
 * the error and to display it on the interface. If properly used, could provide an extensive error description
 * the class is not intended to be instantiated
 *
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://www.ffphp.com
 */
abstract class ErrorHandler
{
	/**
	 * @var array<string, array> All the error handled by the class, with their respective data
	 */
	static array 	$errors_handled 	= array();

	static ?int		$max_recursion			= null;
	static bool 	$on_non_critical_do_500	= false;
	static bool 	$on_non_critical_exit	= false;
	static bool 	$on_critical_do_500		= false;
	static bool 	$on_critical_exit		= true;

	/**
	 * @var bool hide error from the interface (if you enable this, it's advised to enable at least one logging
	 * mechanism)
	 */
	static bool 	$view_hide			= false;
	/**
	 * @var bool display just a small header in the interface. If dump is enabled, it will be shown a reference
	 * to the error
	 */
	static bool 	$view_minimal		= false;
	/**
	 * @var string|null an optional html file for the minimal errors
	 */
	static ?string	$minimal_tpl_path	= null;
	/**
	 * @var string can be "auto", "html" or "txt"
	 */
	static string	$view_format		= "auto";

	/**
	 * @var bool when enabled, an extensive error report will be written as a file on the disk
	 */
	static bool 	$dump				= false;
	/**
	 * @var string|null the directory where the files will be placed
	 */
	static ?string 	$dump_path 			= null;
	/**
	 * @var string the format of the dump files
	 */
	static string 	$dump_format 		= "html"; // can be "html" or "txt"
	/**
	 * @internal
	 * @var mixed|null the dump file pointer
	 */
	static mixed 	$dump_fp			= null; // private

	/**
	 * @var bool if enabled, the errors will be logged to the system using the FF Log class
	 */
	static bool $log_enabled = false;

	/**
	 * @var string the name of the default Log entity to be used. By default, this entity write to STDOUT/STDERR,
	 * so in order to log to a file (advised when using a service like apache) it must be customized.
	 * Refer to the Log help to understand how to do it
	 */
	static string $log_default = "default";

	/** @var array|null[] allow to customize the Log entity per message type */
	static array $log_endpoints = [
		E_ERROR 			=> null,
		E_RECOVERABLE_ERROR => null,
		E_WARNING 			=> null,
		E_PARSE 			=> null,
		E_NOTICE 			=> null,
		E_STRICT 			=> null,
		E_DEPRECATED 		=> null,
		E_CORE_ERROR 		=> null,
		E_CORE_WARNING 		=> null,
		E_COMPILE_ERROR 	=> null,
		E_COMPILE_WARNING 	=> null,
		E_USER_ERROR 		=> null,
		E_USER_WARNING 		=> null,
		E_USER_NOTICE 		=> null,
		E_USER_DEPRECATED 	=> null,
	];

	/**
	 * @var array specify the log level per message type
	 */
	static array $log_levels = [
		E_ERROR 			=> constLogLevels::LOG_LEVEL_ERROR,
		E_RECOVERABLE_ERROR => constLogLevels::LOG_LEVEL_ERROR,
		E_WARNING 			=> constLogLevels::LOG_LEVEL_WARN,
		E_PARSE 			=> constLogLevels::LOG_LEVEL_ERROR,
		E_NOTICE 			=> constLogLevels::LOG_LEVEL_INFO,
		E_STRICT 			=> constLogLevels::LOG_LEVEL_DEBUG,
		E_DEPRECATED 		=> constLogLevels::LOG_LEVEL_DEBUG,
		E_CORE_ERROR 		=> constLogLevels::LOG_LEVEL_ERROR,
		E_CORE_WARNING 		=> constLogLevels::LOG_LEVEL_WARN,
		E_COMPILE_ERROR 	=> constLogLevels::LOG_LEVEL_ERROR,
		E_COMPILE_WARNING 	=> constLogLevels::LOG_LEVEL_WARN,
		E_USER_ERROR 		=> constLogLevels::LOG_LEVEL_ERROR,
		E_USER_WARNING 		=> constLogLevels::LOG_LEVEL_WARN,
		E_USER_NOTICE 		=> constLogLevels::LOG_LEVEL_INFO,
		E_USER_DEPRECATED 	=> constLogLevels::LOG_LEVEL_DEBUG,
	];

	/**
	 * @internal
	 * @var int the count of error shown between view and dump
	 */
	static int $error_count = 0;

	private function __construct() // forbids instantiating the class
	{
	}

	/**
	 * start to watch for errors. It cannot be called twice
	 */
	static function init(): void
	{
		set_error_handler("FF\\Core\\Common\\ErrorHandler::errorHandler", ERRORS_HANDLED);
		set_exception_handler("FF\\Core\\Common\\ErrorHandler::exceptionHandler");
		register_shutdown_function("FF\\Core\\Common\\ErrorHandler::fatalHandler");
		error_reporting(E_ALL ^ ERRORS_SYSTEM);
	}

	/**
	 * The function responsible to raise user defined exceptions. The exception will not be raised for real, but
	 * the handler will be called anyway
	 *
	 * @param string $err_str the error description
	 * @param int $err_no error code: E_USER_ERROR, E_USER_WARNING or E_USER_NOTICE
	 * @param object|null $context a context obj
	 * @param array|null $variables the variables defined in the local scope, usually passed through get_defined_vars()
	 */
	static function raise(string $err_str,
						  #[ExpectedValues(values: [E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE, E_USER_DEPRECATED])]
						  int    $err_no = E_USER_ERROR,
						  object $context = NULL,
						  array  $variables = NULL)
	{
		switch ($err_no)
		{
			case E_USER_ERROR:
			case E_USER_WARNING:
			case E_USER_NOTICE:
			case E_USER_DEPRECATED:
				break;

			default:
				self::raise("Wrong error type, only E_USER_* supported");
				return;
		}

		$id = self::setError(
			err_no: $err_no,
			err_str: $err_str,
			type: constErrorTypes::USER,
			context: $context,
			variables: $variables,
			trace: debug_backtrace()
		);

		self::process($id);
	}

	/**
	 * @param \Exception $ex
	 */
	static function exceptionHandler(\Throwable $ex)
	{
		$id = self::setError(
			err_no: constErrors::EXCEPTION,
			err_str: $ex::class . ": " . $ex->getMessage(),
			type: constErrorTypes::EXCEPTION,
			trace: array_merge([[
				"file" => $ex->getFile(),
				"line" => $ex->getLine()
			]], $ex->getTrace())
		);

		self::process($id);
	}

	/* Since this point, every function is not meant to be called directly */

	/**
	 * https://stackoverflow.com/questions/277224/how-do-i-catch-a-php-fatal-e-error-error
	 */
	static function fatalHandler() : void
	{
		if (null !== ($error = error_get_last()))
		{
			if (!($error["type"] & ERRORS_HANDLED))
				return;

			$id = self::setError(
				err_no: $error["type"],
				err_str: $error["message"],
				type: constErrorTypes::SYSTEM,
				trace:  [[
					"file" => $error['file'],
					"line" => $error['line'],
				]]
			);
			error_clear_last();
			self::process($id);
		}
	}

	/**
	 * @param string|int $err_no
	 * @param string $err_str
	 * @param string $err_file
	 * @param int $err_line
	 * @return false|void
	 */
	static function errorHandler(string|int $err_no, string $err_str, string $err_file, int $err_line): bool
	{
		if (($err_no & ERRORS_SYSTEM) && !(error_reporting() & $err_no)) {
			return false; // Silenced with @, resume script
		}

		$id = self::setError(
			err_no: $err_no,
			err_str: $err_str,
			type: constErrorTypes::ERROR,
			trace: [
				[
					"file" => $err_file,
					"line" => $err_line,
				]
			]
		);

		error_clear_last();
		self::process($id); // with a critical error, the function will not come back

		return false;
	}

	/**
	 * @param int $err_no
	 * @param string $err_str
	 * @param int $type
	 * @param object|null $context
	 * @param array|null $variables
	 * @param array|null $trace
	 * @return string
	 */
	protected static function setError(int     $err_no,
									   string  $err_str,
									   #[ExpectedValues(valuesFromClass: constErrorTypes::class)]
									   int 	   $type,
									   ?object $context = null,
									   ?array  $variables = null,
									   ?array  $trace = null,
	): string
	{
		$id = uniqid(rand(), true);

		self::$errors_handled[$id]["errno"] 		= $err_no;
		self::$errors_handled[$id]["description"] 	= $err_str;
		self::$errors_handled[$id]["type"] 			= $type;
		self::$errors_handled[$id]["context"] 		= $context;
		self::$errors_handled[$id]["variables"] 	= $variables;
		self::$errors_handled[$id]["trace"] 		= $trace;

		$tmp = get_defined_constants();
		self::compactConstants($tmp);
		self::$errors_handled[$id]["constants"] = $tmp;

		return $id;
	}

	static function process(string $id): void
	{
		$error = self::$errors_handled[$id];

		if (self::$log_enabled || self::dumpEnabled() || !self::$view_hide)
		{
			self::$error_count++;

			if (self::$log_enabled)
			{
				Log::get(self::$log_endpoints[$error['errno']] ?? self::$log_default)?->error(
					code: $error['errno'],
					string: $error['description'],
					additional_data: $error['trace'] ? $error['trace'][0] : null,
					level: self::$log_levels[$error['errno']]
				);
			}

			$dump_format = null;

			if (self::dumpEnabled())
			{
				@mkdir(self::$dump_path, 0777, true);
				self::$dump_fp = @fopen(self::$dump_path . "/" . $id . ".dump." . self::$dump_format, "a");
				$dump_format = self::$dump_format;
			}

			$view_format = self::$view_format;
			if ($view_format === "auto")
			{
				if (defined(SAPI))
				{
					$view_format = match (SAPI) {
						"Cli" => "text",
						"Http" => "html",
					};
				}
				else
				{
					if (PHP_SAPI === "cli")
						$view_format = "txt";
					else
						$view_format = "html";
				}
			}

			if ($dump_format === "html" || ($view_format === "html" && !self::hideEnabled()))
				self::displayHTML($id, $error, $dump_format === "html", $view_format === "html" && !self::hideEnabled());

			if ($dump_format === "txt" || ($view_format === "txt" && !self::hideEnabled()))
				self::displayTXT($id, $error, $dump_format === "txt", $view_format === "txt" && !self::hideEnabled());

			if (self::dumpEnabled())
				@fclose(self::$dump_fp);

			if (self::hideEnabled() && self::$view_minimal)
			{
				if (strlen(self::$minimal_tpl_path) && is_file(self::$minimal_tpl_path))
				{
					readfile(self::$minimal_tpl_path);
				}
				else
				{
					if ($view_format === "html")
						echo "<pre>";
					else
						echo "\n\n";
					echo "-=| FORMS FRAMEWORK |=- ERROR CATCHED";
					if (self::dumpEnabled())
					{
						echo "\nID: " . $id . "\n\n";
						echo "This error has been logged and will be fixed as soon as possible.\n";
						echo "If you are in a hurry, please contact support and pass this ID. Thanks in advice for your help";
					}
					else
					{
						echo " #" . count(self::$errors_handled) . "\n\n";
						echo self::$errors_handled[$id]["description"] . "\n\n";
						echo "Please contact support and report this page. Thanks in advice for your help";
					}
					if ($view_format === "html")
						echo "</pre>";
					else
						echo "\n\n";
				}
			}
		}

		switch ($error['errno']) {
			case E_RECOVERABLE_ERROR:
			case E_WARNING:
			case E_NOTICE:
			case E_STRICT:
			case E_DEPRECATED:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
			case E_USER_NOTICE:
			case E_USER_DEPRECATED:
				if (self::$on_non_critical_do_500)
					http_response_code(500);

				if (self::$on_non_critical_exit)
					exit($error['errno']);

				return; // try to resume the process

			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			default:
				if (self::$on_critical_do_500)
					http_response_code(500);

				if (self::$on_critical_exit)
					exit($error['errno']);
		}
	}

	static function displayHTMLHeader($to_dump, $to_view)
	{
		self::out(<<<EOD
<!-- Forms Errors Handling Grouping Function -->
<script>
function expand(link, element_name, sub_element, element_ref) {
    let element;
    if (element_name !== null && element_name !== "") {
        element = document.getElementById(element_name);
    } else {
        element = element_ref;
    }
    if (element.style.display === "block") {
        element.style.display = "none";
        link.innerHTML = "<b>[+]</b>";
    } else {
        if (sub_element !== null && sub_element !== "" && element.innerHTML.length === 0) {
            element.innerHTML = document.getElementById(sub_element).innerHTML;
        }
        element.style.display = "block";
        link.innerHTML = "<b>[-]</b>";
    }
}
</script>
EOD
			, $to_dump, $to_view);
	}

	static function displayTXT($id, array $error, $to_dump, $to_view)
	{
		switch ($error["type"])
		{
			case constErrorTypes::USER:
				$offset_function = 1;
				$offset_args = 1;
				break;

			default:
				$offset_function = 0;
				$offset_args = 0;
		}

		$err_file = $error["trace"][0]["file"];
		$err_line = $error["trace"][0]["line"];
		$err_func = $error["trace"][$offset_function]["function"] ?? null;
		$err_args = $error["trace"][$offset_args]["args"] ?? null;

		$indent = "  ";


		self::out("-=| FORMS FRAMEWORK |=- ERROR HANDLED #" . (self::dumpEnabled() ? $id : self::$error_count) . " : ", $to_dump, $to_view);
		self::out(
			(constErrors::_REVERSE_SHORT[$error["errno"]] ?? constErrors::_REVERSE_SHORT[constErrors::UNKNOWN])
			. " - " .
			(constErrors::_DESCR[$error["errno"]] ?? constErrors::_DESCR[constErrors::UNKNOWN]) . "\n", $to_dump, $to_view);

		// DISPLAY HEADER INFORMATION
		self::out($indent . "Message: " . $error["description"] . "\n", $to_dump, $to_view);
		self::out($indent . "File: " . $err_file . "\n", $to_dump, $to_view);
		self::out($indent . "Line: " . $err_line . "\n", $to_dump, $to_view);
		if (strlen($err_func))
			self::out($indent . "Func: " . $err_func . "\n", $to_dump, $to_view);

		// DISPLAY FUNCTION ARGUMENTS
		if (is_array($err_args) && count($err_args))
		{
			self::out($indent . "Args:\n", $to_dump, $to_view);
			self::structPrintTXT(to_dump: $to_dump, to_view: $to_view, arg: $err_args, recursion: 0, base_indent: $indent);
		}

		// DISPLAY BACKTRACE
		if (is_array($error["trace"]) && count($error["trace"]) > 1)
		{
			self::out("\n" . $indent . "Backtrace:\n", $to_dump, $to_view);
			foreach ($error["trace"] as $key => $value)
			{
				if (!$key) // skip first
					continue;

				if ($key > 1)
					self::out("\n", $to_dump, $to_view);

				$sub_indent = str_repeat($indent, 2);

				self::out($sub_indent . "File: " . $value["file"] . "\n", $to_dump, $to_view);
				self::out($sub_indent . "Line: " . $value["line"] . "\n", $to_dump, $to_view);
				if (isset($error["trace"][$key + $offset_function]["function"]))
					self::out($sub_indent . "Func: " . $error["trace"][$key + $offset_function]["function"] . "\n", $to_dump, $to_view);
				if (isset($error["trace"][$key + $offset_args]["args"]) && is_array($error["trace"][$key + $offset_args]["args"]) && count($error["trace"][$key + $offset_args]["args"]))
				{
					self::out($sub_indent . "Used Args:\n", $to_dump, $to_view);
					self::structPrintTXT(to_dump: $to_dump, to_view: $to_view, arg: $error["trace"][$key + $offset_args]["args"], recursion: 0, base_indent: $sub_indent);
				}
			}
		}

		if ($to_view)
			return;

		// DISPLAY FUNCTION VARIABLES
		if (isset($error["variables"]) && is_array($error['variables']) && count($error["variables"]))
		{
			if (isset($error["variables"]["GLOBALS"]))
				$error["variables"] = self::removeGlobals($error["variables"]);
			self::out("\n" . $indent . "Variables:\n", $to_dump, $to_view);
			self::structPrintTXT(to_dump: $to_dump, to_view: $to_view, arg: $error["variables"], recursion: 0, base_indent: $indent);
		}

		// DISPLAY GLOBAL VARIABLES
		if (!isset($error["variables"]["GLOBALS"]))
		{
			$tmp_globals = self::removeGlobals($GLOBALS);
			self::out("\n" . $indent . "Globals:\n", $to_dump, $to_view);
			self::structPrintTXT($to_dump, $to_view, $tmp_globals, 0, $indent);
		}

		// DISPLAY CONSTANTS
		if ($error["constants"] !== NULL)
		{
			self::out("\n" . $indent . "Constants:\n", $to_dump, $to_view);
			self::structPrintTXT(to_dump: $to_dump, to_view: $to_view, arg: $error["constants"], recursion: 0, base_indent: $indent);
		}

	}

	static function displayHTML($id, array $error, $to_dump, $to_view)
	{
		switch ($error["type"])
		{
			case constErrorTypes::USER:
				$offset_function = 1;
				$offset_args = 1;
				break;

			default:
				$offset_function = 0;
				$offset_args = 0;
		}

		$err_file = $error["trace"][0]["file"];
		$err_line = $error["trace"][0]["line"];
		$err_func = $error["trace"][$offset_function]["function"] ?? null;
		$err_args = $error["trace"][$offset_args]["args"] ?? null;

		if ($to_view && self::$error_count === 1)
			self::displayHTMLHeader(false, true);

		if ($to_dump)
			self::displayHTMLHeader(true, false);

		// DISPLAY BOX
		self::out('<div style="display: block; background-color: #AAAAAA; border: 1px solid #FF0000; padding: 10px;font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;">
<div style="display: block; background-color: #FF0000; border: 0; padding: 5px; color: #FFFFFF;">
<b>-=| FORMS FRAMEWORK |=-</b> ERROR HANDLED #' . (self::dumpEnabled() ? $id : self::$error_count) . '
</div>', $to_dump, $to_view);

		// DISPLAY HEADER INFORMATION
		self::out('<p>' .
			(constErrors::_REVERSE_SHORT[$error["errno"]] ?? constErrors::_REVERSE_SHORT[constErrors::UNKNOWN])
			. " - " .
			(constErrors::_DESCR[$error["errno"]] ?? constErrors::_DESCR[constErrors::UNKNOWN]) . "<br />" . $error["description"] . '</p>', $to_dump, $to_view);
		self::out('<table style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;">', $to_dump, $to_view);
		self::out('<tr><td style="width: 110px; vertical-align: top;"><b>File:</b></td><td>' . $err_file . '</td></tr>', $to_dump, $to_view);
		self::out('<tr><td style="vertical-align: top;"><b>Line:</b></td><td>' . $err_line . '</td></tr>', $to_dump, $to_view);
		if (strlen($err_func))
			self::out('<tr><td style="vertical-align: top;"><b>Func:</b></td><td>' . $err_func . '</td></tr>', $to_dump, $to_view);

		// DISPLAY FUNCTION ARGUMENTS
		if (is_array($err_args) && count($err_args))
		{
			self::out('<tr><td style="vertical-align: top;"><b>Func Args:</b></td><td>', $to_dump, $to_view);
			self::structPrintHTML($to_dump, $to_view, $err_args, 0, true);
			self::out('</td></tr>', $to_dump, $to_view);
		}

		// DISPLAY FILE SOURCE
/*		self::out('<tr><td style="vertical-align: top;"><b>Source: <a href="javascript:void(0);" onclick="expand(this, \'div_source_' . self::$error_count . '\');">[+]</a></b></td><td><div id="div_source_' . self::$error_count . '" style="display: none; border: 1px solid black; background-color: #FFFFFF; overflow-x: scroll;"><code>', $to_dump, $to_view);
		$startline = $errline - 10;
		if ($startline < 0)
			$startline = 0;
		$endline = $errline + 10;
		$code = highlight_file($errfile, true);
		for ($i = 0; $i < strlen($code); $i++)
			{
				$buffer .= $code[$i];
				if (substr($buffer, -6) == '<br />')
					{
						$tmp = count($codeln);
						if ($tmp + 1 == $errline)
							$codeln[$tmp] = '<span style="font-weight: bold; color: #000000; border-right: 1px solid black; background-color: #AAAAAA;">&nbsp;' . str_replace(" ", "&nbsp;", sprintf("%5s", $tmp + 1)) . '&nbsp;</span>';
						else
							$codeln[$tmp] = '<span style="font-weight: bold; color: #000000; border-right: 1px solid black; background-color: #DDDDDD;">&nbsp;' . str_replace(" ", "&nbsp;", sprintf("%5s", $tmp + 1)) . '&nbsp;</span>';
						$codeln[$tmp] .= $buffer;
						$buffer = "";
					}
			}
		if ($code[$i] != "\n")
			$codeln[count($codeln)] = $buffer;
		for ($i = $startline - 1; $i <= $endline - 1; $i++)
			{
				if ($i + 1 == $errline)
					self::out("<div style='background-color: #ffff66; width: 100%;'>" . $codeln[$i] . "</div>", $to_dump, $to_view);
				else
					self::out($codeln[$i], $to_dump, $to_view);
			}
		self::out('</code></div></td></tr>', $to_dump, $to_view);
 */
		// DISPLAY FUNCTION VARIABLES
		if (isset($error["variables"]) && is_array($error['variables']) && count($error["variables"]))
		{
			if (isset($error["variables"]["GLOBALS"]))
				$error["variables"] = self::removeGlobals($error["variables"]);
			self::out('<tr><td style="vertical-align: top;"><b>Variables: <a href="javascript:void(0);" onclick="expand(this, \'div_variables_' . self::$error_count . '\');">[+]</a></b></td><td><div id="div_variables_' . self::$error_count . '" style="display: none; overflow: hidden;">', $to_dump, $to_view);
			self::structPrintHTML($to_dump, $to_view, $error["variables"], 0, true);
			self::out('</div></td></tr>', $to_dump, $to_view);
		}

		// DISPLAY GLOBAL VARIABLES
		if (!isset($error["variables"]["GLOBALS"]))
		{
			$tmp_globals = self::removeGlobals($GLOBALS);
			self::out('<tr><td style="vertical-align: top;"><b>Globals: <a href="javascript:void(0);" onclick="expand(this, \'div_globals_' . self::$error_count . '\');">[+]</a></b></td><td><div id="div_globals_' . self::$error_count . '" style="display: none; overflow: hidden;">', $to_dump, $to_view);
			self::structPrintHTML($to_dump, $to_view, $tmp_globals, 0, true);
			self::out('</div></td></tr>', $to_dump, $to_view);
		}

		// DISPLAY CONSTANTS
		if ($error["constants"] !== NULL)
		{
			self::out('<tr><td style="vertical-align: top;"><b>Constants: <a href="javascript:void(0);" onclick="expand(this, \'div_constants_' . self::$error_count . '\');">[+]</a></b></td><td><div id="div_constants_' . self::$error_count . '" style="display: none; overflow: hidden;">', $to_dump, $to_view);
			self::structPrintHTML($to_dump, $to_view, $error["constants"], 0, true);
			self::out('</div></td></tr>', $to_dump, $to_view);
		}

		// DISPLAY BACKTRACE
		if (is_array($error["trace"]) && count($error["trace"]) > 1)
		{
			self::out('<tr><td style="vertical-align: top;"><b>Backtrace: <a href="javascript:void(0);" onclick="expand(this, \'div_backtrace_' . self::$error_count . '\');">[+]</a></b></td><td><div id="div_backtrace_' . self::$error_count . '" style="display: none; overflow: hidden;">', $to_dump, $to_view);
			foreach ($error["trace"] as $key => $value)
			{
				if (!$key) // skip first
					continue;

				self::out('<table style="border: 1px solid black; font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px; width: 100%; margin-bottom: 10px;">', $to_dump, $to_view);
				self::out('<tr><td style="width: 100px; vertical-align: top;"><b>File:</b></td><td>' . $value["file"] . '</td></tr>', $to_dump, $to_view);
				self::out('<tr><td style="vertical-align: top;"><b>Line:</b></td><td>' . $value["line"] . '</td></tr>', $to_dump, $to_view);
				if (isset($error["trace"][$key + $offset_function]["function"]))
					self::out('<tr><td style="vertical-align: top;"><b>Func:</b></td><td>' . $error["trace"][$key + $offset_function]["function"] . '</td></tr>', $to_dump, $to_view);
				if (isset($error["trace"][$key + $offset_args]["args"]) && is_array($error["trace"][$key + $offset_args]["args"]) && count($error["trace"][$key + $offset_args]["args"]))
				{
					self::out('<tr><td style="vertical-align: top;"><b>Used Args:</b></td><td>', $to_dump, $to_view);
					self::structPrintHTML($to_dump, $to_view, $error["trace"][$key + $offset_args]["args"], 0, true);
					self::out('</td></tr>', $to_dump, $to_view);
				}
				self::out('</table>', $to_dump, $to_view);
			}
			self::out('</div></td></tr>', $to_dump, $to_view);
		}
		self::out('</table>', $to_dump, $to_view);
		self::out('</div>', $to_dump, $to_view);
	}

	static function removeGlobals($params): array
	{
		$res = null;
		if (is_array($params) && count($params))
		{
			$res = array();
			foreach ($params as $key => $value)
			{
				if (!(
					   (isset($params["_ENV"][$key]) && $params["_ENV"][$key] === $value)
					|| (isset($params["_SERVER"][$key]) && $params["_SERVER"][$key] === $value)
					|| (isset($params["_COOKIE"][$key]) && $params["_COOKIE"][$key] === $value)
					|| (isset($params["_POST"][$key]) && $params["_POST"][$key] === $value)
					|| (isset($params["_GET"][$key]) && $params["_GET"][$key] === $value)
					|| (isset($params["_FILES"][$key]) && $params["_FILES"][$key] === $value)
					|| (isset($params["_SESSION"][$key]) && $params["_SESSION"][$key] === $value)
					/*|| (str_starts_with($key, APPID))*/
					|| $key === "HTTP_ENV_VARS"
					|| $key === "HTTP_SERVER_VARS"
					|| $key === "HTTP_COOKIE_VARS"
					|| $key === "HTTP_POST_VARS"
					|| $key === "HTTP_GET_VARS"
					|| $key === "HTTP_FILES_VARS"
					|| $key === "HTTP_SESSION_VARS"
					|| $key === "GLOBALS"
				))
					$res[$key] = $value;
			}
			reset($params);
		}
		return $res;
	}

	static function compactConstants(&$params)
	{
		ksort($params);
		
		foreach ($params as $key => $value)
		{
			if (str_contains($key, "_"))
			{
				// underscores present, find the right "node"
				unset($params[$key]);

				$tmp = $key;
				$node = $params;
				while(($offset = strpos($tmp, "_")) !== FALSE)
				{
					$subkey = substr($tmp, 0, $offset) . "<b></b>";
					$tmp = substr($tmp, $offset + 1);
					if (!isset($node[$subkey]))
						$node[$subkey] = array();

					$node = $node[$subkey];
				}

				$node[$key] = $value;
			}
		}
		reset($params);
		self::compactConstantsReduce($params);
	}
	
	static function compactConstantsReduce(&$node)
	{
		foreach ($node as $key => $value)
		{
			if (is_array($node[$key]))
			{
				if (count($node[$key]) == 1)
				{
					$tmp_key = $key;
					while(is_array($node[$tmp_key]) && count($node[$tmp_key]) == 1)
					{
						$newkey = key($node[$tmp_key]);
						$newvalue = $node[$tmp_key];
						$node[$newkey] = $newvalue;
						unset($node[$tmp_key]);
						$tmp_key = $newkey;
					}
					
				} else {
					self::compactConstantsReduce($node[$key]);
				}
			}
		}
		reset($node);
		ksort($node);
	}

	/**
	 * @param bool $to_dump
	 * @param bool $to_view
	 * @param array|object $arg
	 * @param int $recursion
	 * @param bool $display_lines
	 */
	static function structPrintHTML(bool $to_dump, bool $to_view, array|object &$arg, int $recursion = 0, bool $display_lines = true)
	{
		static $errors_objects 	= array();
		static $errors_arrays 	= array();

		// FIRST, get members
		
		if (is_array($arg))
		{
			if (!$recursion && $display_lines)
				self::out('<div style="border-top: 1px dashed black; margin-top: 2px; margin-bottom: 2px;"></div>', $to_dump, $to_view);

			$vars = $arg;
		}
		elseif (is_object($arg))
		{
			$vars = get_object_vars($arg);
			ksort($vars);
		}
		else
		{
			var_dump($arg);
			die("UNKNOWN IN STRUCT PRINT!");
		}
			
		foreach ($vars as $key => $value)
		{
			self::out("[$key] => ", $to_dump, $to_view);
			if (is_object($value))
			{
				self::out("Object <b>[type = " . get_class($value) . "]</b> ", $to_dump, $to_view);
				if (self::$max_recursion !== NULL && $recursion >= self::$max_recursion)
					self::out("<b>MAX RECURSION</b>", $to_dump, $to_view);
				elseif(get_class($value) == "com")
				{
					self::out("<b>SKIPPED</b>", $to_dump, $to_view);
				}
				else
				{
					$bFind = FALSE;
					/*$obj_id = get_class($value) . "_" . FormsCommon_get_object_id($value);
					if (isset($errors_objects[$obj_id]))
						$bFind = $errors_objects[$obj_id]["id"];*/

					foreach ($errors_objects as $subkey => $subvalue)
					{
						if ($errors_objects[$subkey]["ref"] === $value)
						{
							$obj_id = $subkey;
							$bFind = $errors_objects[$subkey]["id"];
							self::out(" ID #" . $obj_id . "", $to_dump, $to_view);
							break;
						}
					}
					reset($errors_objects);

					if ($bFind === FALSE)
					{
						$bFind = uniqid(rand(), true);
						$obj_id = count($errors_objects);
						$errors_objects[$obj_id] = array("id" => $bFind, "ref" => $value);
						self::out(" ID #" . $obj_id . "", $to_dump, $to_view);
						self::out('<div id="obj_' . $bFind . '" style="display: none;">', $to_dump, $to_view);
						self::structPrintHTML($to_dump, $to_view, $value, $recursion + 1, true);
						self::out('</div>', $to_dump, $to_view);
					}

					self::out('<a href="javascript:void(0);" onclick="expand(this, null, \'obj_' . $bFind . '\', this.nextSibling);"><b>[+]</b></a><div style="padding-left: 40px; display: none;"></div>', $to_dump, $to_view);
				}
				self::out("<br />", $to_dump, $to_view);
			}
			else if (is_array($value))
			{
				self::out("Array <b>[count = " . count($value) . "]</b> ", $to_dump, $to_view);
				$bFind = FALSE;
				foreach ($errors_arrays as $subkey => $subvalue)
				{
					if ($subvalue === $value)
					{
						$bFind = $subkey;
						break;
					}
				}
				reset($errors_arrays);

				if ($bFind === FALSE)
				{
					if (self::$max_recursion !== NULL && $recursion >= self::$max_recursion)
						self::out("<b>MAX RECURSION</b>", $to_dump, $to_view);
					else if (count($value))
					{
						$bFind = uniqid(rand(), true);
						$errors_arrays[$bFind] = $value;
						self::out('<div id="arr_' . $bFind . '" style="display: none;">', $to_dump, $to_view);
						self::structPrintHTML($to_dump, $to_view, $value, $recursion + 1, FALSE);
						self::out('</div>', $to_dump, $to_view);
					}
				}

				if ($bFind)
					self::out('<a href="javascript:void(0);" onclick="expand(this, null, \'arr_' . $bFind . '\', this.nextSibling);"><b>[+]</b></a><div style="padding-left: 40px; display: none;"></div>', $to_dump, $to_view);
				self::out("<br />", $to_dump, $to_view);
			}
			else if ($value === NULL)
				self::out("NULL<br />", $to_dump, $to_view);
			else if ($value === FALSE)
				self::out("FALSE<br />", $to_dump, $to_view);
			else if ($value === TRUE)
				self::out("true<br />", $to_dump, $to_view);
			else if (is_string($value))
			{
				if (
						str_contains(strtolower($key), "password")
						|| $key === "_crypt_Ku_"
						|| $key === "_crypt_KSu_"
					)
					self::out("<b>PROTECTED</b><br />", $to_dump, $to_view);
				else
					self::out('"' . specialchars($value) . "\"<br />", $to_dump, $to_view);
			}
			else if (is_resource($value))
				self::out("Resource <b>[type = " . get_resource_type($value) . "]</b><br />", $to_dump, $to_view);
			else
				self::out($value . "<br />", $to_dump, $to_view);

			if (!$recursion && $display_lines)
				self::out('<div style="border-top: 1px dashed black; margin-top: 2px; margin-bottom: 2px;"></div>', $to_dump, $to_view);
		}
		reset($vars);

		if (!$recursion)
		{
			self::out("</code>", $to_dump, $to_view);
			$errors_objects = array(); // free memory
			$errors_arrays 	= array();
		}
	}

	/**
	 * @param bool $to_dump
	 * @param bool $to_view
	 * @param array|object $arg
	 * @param int $recursion
	 * @param string $base_indent
	 */
	static function structPrintTXT(bool $to_dump, bool $to_view, array|object &$arg, int $recursion = 0, string $base_indent = "")
	{
		static $errors_objects 	= array();
		static $errors_arrays 	= array();

		// FIRST, get members

		if (is_array($arg))
		{
			$vars = $arg;
		}
		elseif (is_object($arg))
		{
			$vars = get_object_vars($arg);
			ksort($vars);
		}
		else
		{
			var_dump($arg);
			die("UNKNOWN IN STRUCT PRINT!");
		}

		$indent = $base_indent . str_repeat("     ", $recursion + 1);

		foreach ($vars as $key => $value)
		{
			self::out($indent . "[$key] => ", $to_dump, $to_view);
			if (is_object($value))
			{
				self::out("Object [type = " . get_class($value) . "] ", $to_dump, $to_view);

				if (self::$max_recursion !== NULL && $recursion >= self::$max_recursion)
				{
					self::out("MAX RECURSION\n", $to_dump, $to_view);
				}
				elseif (get_class($value) == "com")
				{
					self::out("SKIPPED\n", $to_dump, $to_view);
				}
				else
				{
					foreach ($errors_objects as $subkey => $subvalue)
					{
						if ($errors_objects[$subkey]["ref"] === $value)
						{
							$obj_id = $subkey;
							self::out("ID #" . $obj_id . " ALREADY PRINTED\n", $to_dump, $to_view);
							continue 2;
						}
					}

					$bFind = uniqid(rand(), true);
					$obj_id = count($errors_objects);
					$errors_objects[$obj_id] = array("id" => $bFind, "ref" => $value);
					self::out("ID #" . $obj_id . "\n", $to_dump, $to_view);
					self::structPrintTXT(to_dump: $to_dump, to_view: $to_view, arg: $value, recursion: $recursion + 1, base_indent: $base_indent);
				}
			}
			else if (is_array($value))
			{
				self::out("Array [count = " . count($value) . "]\n", $to_dump, $to_view);

				if (self::$max_recursion !== NULL && $recursion >= self::$max_recursion)
				{
					self::out("MAX RECURSION\n", $to_dump, $to_view);
				}
				else
				{
					foreach ($errors_arrays as $subkey => $subvalue)
					{
						if ($subvalue === $value) {
							self::out("ALREADY PRINTED\n", $to_dump, $to_view);
							continue 2;
						}
					}

					if (count($value))
					{
						$bFind = uniqid(rand(), true);
						$errors_arrays[$bFind] = $value;
						self::structPrintTXT(to_dump: $to_dump, to_view: $to_view, arg: $value, recursion: $recursion + 1, base_indent: $base_indent);
					}
				}
			}
			else if ($value === NULL)
				self::out("NULL\n", $to_dump, $to_view);
			else if ($value === FALSE)
				self::out("FALSE\n", $to_dump, $to_view);
			else if ($value === TRUE)
				self::out("true\n", $to_dump, $to_view);
			else if (is_string($value))
			{
				if (
						str_contains(strtolower($key), "password")
						|| $key === "_crypt_Ku_"
						|| $key === "_crypt_KSu_"
					)
					self::out("PROTECTED\n", $to_dump, $to_view);
				else
					self::out('"' . $value . "\"\n", $to_dump, $to_view);
			}
			else if (is_resource($value))
				self::out("Resource [type = " . get_resource_type($value) . "]\n", $to_dump, $to_view);
			else
				self::out($value . "\n", $to_dump, $to_view);
		}
		reset($vars);

		if (!$recursion)
		{
			$errors_objects = array(); // free memory
			$errors_arrays 	= array();
		}
	}

	static function dumpEnabled(): bool
	{
		return self::$dump && self::$dump_path && !defined("FF_URLPARAM_DEBUG");
	}
	
	static function hideEnabled(): bool
	{
		return self::$view_hide && !defined("FF_URLPARAM_DEBUG");
	}

	static function out($text, $to_dump, $to_view): void
	{
		if ($to_dump)
			@fwrite(self::$dump_fp, $text);

		if ($to_view)
			echo $text;
	}
}