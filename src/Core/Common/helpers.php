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
 * Return a UUID (version 4) using random bytes
 * Note that version 4 follows the format:
 *     xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
 * where y is one of: [8, 9, A, B]
 *
 * We use (random_bytes(1) & 0x0F) | 0x40 to force
 * the first character of hex value to always be 4
 * in the appropriate position.
 *
 * For 4: http://3v4l.org/q2JN9
 * For Y: http://3v4l.org/EsGSU
 * For the whole shebang: https://3v4l.org/LNgJb
 *
 * @ref https://stackoverflow.com/a/31460273/2224584
 * @ref https://paragonie.com/b/JvICXzh_jhLyt4y3
 *
 * @return string
 * @throws \Exception
 */
function uuidv4(): string
{
    return implode('-', [
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(2)),
        bin2hex(chr((ord(random_bytes(1)) & 0x0F) | 0x40)) . bin2hex(random_bytes(1)),
        bin2hex(chr((ord(random_bytes(1)) & 0x3F) | 0x80)) . bin2hex(random_bytes(1)),
        bin2hex(random_bytes(6))
    ]);
}

function IndexOrder($a, $b): ?int
{
	$ret = null;

	if (is_array($a) && isset($a["index"]))
		$a_index = (int)$a["index"];
	elseif (is_object($a) && isset($a->index))
		$a_index = (int)$a->index;
	else
		$a_index = null;

	if (is_array($b) && isset($b["index"]))
		$b_index = (int)$b["index"];
	elseif (is_object($b) && isset($b->index))
		$b_index = (int)$b->index;
	else
		$b_index = null;
	
	if($a_index === null && $b_index === null)
	    $ret = 0;
	elseif($a_index === null)
	    $ret = 1;
	elseif($b_index === null)
	    $ret = -1;
	elseif($a_index === $b_index)
	    $ret = 0;
	else 
    	$ret = ($a_index < $b_index) ? -1 : 1;
    	
	if ($ret === 0 && is_array($a) && isset($a["counter"]) && isset($b["counter"]))
    	$ret = ((int)$a["counter"] < (int)$b["counter"]) ? -1 : 1;
	elseif ($ret === 0 && is_object($a) && isset($a->counter) && isset($b->counter))
    	$ret = ((int)$a->counter < (int)$b->counter) ? -1 : 1;
    	
	return $ret;
}

/**
 * get parent'dir with consistent slashes and excluding specials (like .), no matter if windows or linux
 * @param string $path
 * @return string
 */
function dirname(string $path): string
{
	static $windows = null;
	if ($windows === null)
	{
		if(\dirname("/") == "\\")
			$windows = true;
		else
			$windows = false;
	}

	if (str_ends_with($path, "/"))
		$res = substr($path, 0, -1);
	else
		$res = \dirname($path);

	if($windows)
		$res = str_replace("\\", "/", $res);

	if($res == ".")
		$res = "";

	return $res;
}

function specialchars($string, $quote_style = ENT_QUOTES, $charset = null, $double_encode = true, $remove_np = true): string
{
	if ($charset === null)
		$charset = FF_DEFAULT_CHARSET;

	$string = charset_encode($string, $charset);

	if ($remove_np)
		return preg_replace("/[\x08\x0B\x0C\x0E-\x1F]/", "", htmlspecialchars($string, $quote_style, $charset, $double_encode));
	else
		return htmlspecialchars($string, $quote_style, $charset, $double_encode);
}

function charset_encode($string, $charset = null)
{
	if ($string === null || $string === "")
		return "";

	if (!is_scalar($string))
		ErrorHandler::raise("value is not a String", E_USER_ERROR, null, get_defined_vars());

	if ($charset === null)
		$charset = FF_DEFAULT_CHARSET;

	if (!mb_check_encoding($string, $charset))
	{
		switch ($charset)
		{
			case "UTF-8":
				$string = utf8_encode($string);
				break;

			default:
				ErrorHandler::raise($charset . " encoding not implemented yet", E_USER_ERROR, null, get_defined_vars());
		}
	}

	return $string;
}
