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

namespace FF\Libs\PWA\WebSocket\Common;

use JetBrains\PhpStorm\ArrayShape;

function get_error_string(int $code): string
{
	$ERROR_STRINGS = [
		-1								=> "Unknown Error",
		ERROR_ENCRYPT_WRONGKEY			=> "Wrong Encryption PublicKey",
		ERROR_UNAUTHORIZED				=> "Unauthorized",
		ERROR_NOT_CONNECTED				=> "Connection not established",
		ERROR_ENCRYPT_FAILED			=> "Encryption failed",
		ERROR_SEND						=> "Unable to send data",
		ERROR_CONNECT					=> "Unable to connect",
		ERROR_SERVICE_PATH				=> "Wrong service path, must be a relative url",
		ERROR_RESPONSE_TIMEOUT			=> "Response timeout",
		ERROR_DISCONNECTED				=> "The socket disconnected",
		ERROR_ENCRYPTED_EXPECTED		=> "Encrypted message expected, got plain",
		ERROR_ENCRYPTED_UNEXPECTED		=> "Plain message expected, got encrypted",
		ERROR_UNKNOWN_SYS_MSG			=> "Unknown System Message",
		ERROR_MISMATCHED_ANSWER			=> "Response doesn't match the command sent",
		ERROR_SERVER_ERROR				=> "Server Communication Error",
		ERROR_SERVICE_UNKNWON			=> "Service unknown",
		ERROR_DECRYPT_FAILED			=> "Decryption failed",
		ERROR_PROTOCOL_TOO_NEW			=> "Server protocol is too new",
		ERROR_UNIXSOCK_WRONGPATH		=> "Wrong Path for socket",
		ERROR_WRONG_HANDLER				=> "Wrong handler",
		ERROR_SEND_SOCKET				=> "Cannot send to websocket",
		ERROR_CONTEXT_CREATION			=> "Unable to create SSL context",
		ERROR_ALREADY_STARTED			=> "Server already started",
		ERROR_SERVER_SOCKET				=> "Unable to create Server socket",
		ERROR_CONTROL_INTERFACE			=> "Unable to start Control IF",
		ERROR_UNIXSOCK_EXISTS			=> "Unix socket already exists",
		ERROR_CONTROL_SOCKET			=> "Unable to create Control IF socket",
		ERROR_SERVER_STREAM_SELECT		=> "Error in server select from sockets",
		ERROR_SERVER_ACCEPT				=> "Cannot accept new socket for web client connection",
		ERROR_SERVICE_MISSING_DEST		=> "Missing service destination in routing rule",
		ERROR_CLIENT_HANDSHAKE			=> "Error in web client handshake",
		ERROR_CLIENT_FRAME				=> "Corrupted or wrong web client frame",
		ERROR_CLIENT_PAYLOAD_SIZE		=> "Wrong payload size",
		ERROR_CLIENT_WRONG_OPCODE		=> "The specified opcode is reserved",
		ERROR_COMMAND_TYPE				=> "Unsupported command type, 16 bit uint required",
		ERROR_COMMAND_PARAMETERS		=> "Wrong command parameters format, must be encoded as a valid json",
		ERROR_MESSAGE_FORMAT			=> "Wrong message format, commands must return valid json data",
		ERROR_CONTROL_PROTOCOL			=> "Control protocol violation",
		ERROR_CONTROL_COMMAND_UNKNOWN	=> "Unknown Command",
		ERROR_UNKNOWN_CLIENT			=> "Unknown Client",
		ERROR_UNKNOWN_SOCKET			=> "Unknown Socket",
		ERROR_CONTROL_ACCEPT			=> "Cannot accept control client",
		ERROR_CLIENT_INACTIVE			=> "Client inactive for too long",
		ERROR_SERVICE_NOT_FOUND			=> "Service not found",
		ERROR_MISSING_PARAM				=> "Missing required param",
	];

	return $ERROR_STRINGS[$code] ?? $ERROR_STRINGS[-1];
}
	
function DebugOutput($text, $level = 0, $newline = true, $force_view = false, $debug = false)
{
	if (!$force_view && !DEBUG)
		return;
	
	static $last_indent = "";
	static $last_newline = 1;
	$output = "";

	if ($last_newline)
	{
		$date = \DateTime::createFromFormat('U.u', microtime(TRUE));
		$output .= $date->format('Y-m-d H:i:s.u') . " ";
		
		$indent = "";
		if ($level === null)
		{
			$indent = $last_indent;
		}
		else
		{
            $indent .= str_repeat("\t", $level);
			$last_indent = $indent;
		}

		$output .= $indent;
	}

	$output .= $text . ($newline ? "\n" : "");
	fwrite(STDOUT, $output);
	
	$last_newline = $newline;
}

function DebugError($code, $string = null, $level = null, $additional_data = null)
{
	if ($string === null)
		$string = get_error_string($code);
	
	DebugOutput(text: "ERROR: " . ($code > 0 ? "(" . $code . ") " : "") . $string . ($additional_data !== null ? var_export($additional_data, true) : ""), level: $level, debug: true);
}

if (!function_exists("get_resource_id"))
{
	function get_resource_id($res): bool|int
    {
		if (!is_resource($res))
			return false;
		
		$tmp = strtolower("" . $res);
		if (!str_starts_with($tmp, "resource id #"))
			return false;
		
		return intval(str_replace("resource id #", "", $tmp));
	}
}

#[ArrayShape(["h" => "int", "m" => "float|int", "s" => "int"])]
function microtime_details(float $microtime): array
{
	$hours = intval($microtime / 60 / 60);
	$minutes = intval($microtime / 60) - $hours * 60;
	$seconds = intval($microtime - $hours * 60 * 60 - $minutes * 60);
	return [
		"h" => $hours,
		"m" => $minutes,
		"s" => $seconds
	];
}