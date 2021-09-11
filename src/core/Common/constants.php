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
