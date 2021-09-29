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
/**
 * https://stackoverflow.com/questions/11318768/php-stdout-on-apache
 */
if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'wb'));
if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'wb'));

const STREAM_STDOUT = "STDOUT";
const STREAM_STDERR = "STDERR";

abstract class constLogLevels {
	const LOG_LEVEL_OFF 					= 0;
	const LOG_LEVEL_FATAL 					= 1;
	const LOG_LEVEL_ERROR 					= 2;
	const LOG_LEVEL_WARN 					= 3;
	const LOG_LEVEL_INFO 					= 4;
	const LOG_LEVEL_DEBUG 					= 5;
	const LOG_LEVEL_TRACE 					= 6;
	const LOG_LEVEL_ALL 					= 7;

	const LOG_LEVEL_DESCR = [
		self::LOG_LEVEL_FATAL 	=> "FATAL",
		self::LOG_LEVEL_ERROR 	=> "ERROR",
		self::LOG_LEVEL_WARN 	=> "WARN",
		self::LOG_LEVEL_INFO	=> "INFO",
		self::LOG_LEVEL_DEBUG 	=> "DEBUG",
		self::LOG_LEVEL_TRACE 	=> "TRACE",
	];
}

const ERRORS_SYSTEM = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_STRICT;

abstract class constErrorTypes {
	const ERROR		= 1;
	const EXCEPTION	= 2;
	const SYSTEM	= 3;
	const USER		= 4;
}

abstract class constErrors {
	const UNKNOWN 			= 0;
	const EXCEPTION 		= -1;

	const _DESCR = [
		E_ERROR 			=> "Fatal run-time error",
		E_RECOVERABLE_ERROR => "Catchable fatal error",
		E_WARNING 			=> "Run-time non-fatal error",
		E_PARSE 			=> "Compile-time parse error",
		E_NOTICE 			=> "Run-time notice",
		E_STRICT 			=> "Code suggestion",
		E_DEPRECATED 		=> "Deprecated code",
		E_CORE_ERROR 		=> "Startup fatal error",
		E_CORE_WARNING 		=> "Startup non-fatal error",
		E_COMPILE_ERROR 	=> "Fatal compile-time error",
		E_COMPILE_WARNING 	=> "Non-fatal compile-time error",
		E_USER_ERROR 		=> "User-generated error message",
		E_USER_WARNING 		=> "User-generated warning message",
		E_USER_NOTICE 		=> "User-generated notice message",
		E_USER_DEPRECATED 	=> "User-generated deprecated message",
		self::UNKNOWN 		=> "Unknown error",
		self::EXCEPTION 	=> "Unhandled Exception",
	];

	const _REVERSE_SHORT = [
		E_ERROR 			=> "E_ERROR",
		E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
		E_WARNING 			=> "E_WARNING",
		E_PARSE 			=> "E_PARSE",
		E_NOTICE 			=> "E_NOTICE",
		E_STRICT 			=> "E_STRICT",
		E_DEPRECATED 		=> "E_DEPRECATED",
		E_CORE_ERROR 		=> "E_CORE_ERROR",
		E_CORE_WARNING 		=> "E_CORE_WARNING",
		E_COMPILE_ERROR 	=> "E_COMPILE_ERROR",
		E_COMPILE_WARNING 	=> "E_COMPILE_WARNING",
		E_USER_ERROR 		=> "E_USER_ERROR",
		E_USER_WARNING 		=> "E_USER_WARNING",
		E_USER_NOTICE 		=> "E_USER_NOTICE",
		E_USER_DEPRECATED 	=> "E_USER_DEPRECATED",
		self::UNKNOWN 		=> "UNKNOWN",
		self::EXCEPTION 	=> "EXCEPTION",
	];
}
