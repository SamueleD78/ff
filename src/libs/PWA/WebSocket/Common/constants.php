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

const VERSION							= "1.2.0";
const DEBUG								= true; // this constant is used only in the lib development process

const RCV_BUFFER_SIZE					= 4096;

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
		self::LOG_LEVEL_FATAL => "FATAL",
		self::LOG_LEVEL_ERROR => "ERROR",
		self::LOG_LEVEL_WARN 	=> "WARN",
		self::LOG_LEVEL_INFO	=> "INFO",
		self::LOG_LEVEL_DEBUG => "DEBUG",
		self::LOG_LEVEL_TRACE => "TRACE",
	];
}

const ERROR_ENCRYPT_WRONGKEY			= 1;
const ERROR_UNAUTHORIZED				= 2;
const ERROR_NOT_CONNECTED				= 3;
const ERROR_ENCRYPT_FAILED				= 4;
const ERROR_SEND						= 5;
const ERROR_CONNECT						= 6;
const ERROR_SERVICE_PATH				= 7;
const ERROR_RESPONSE_TIMEOUT			= 8;
const ERROR_DISCONNECTED				= 9;
const ERROR_ENCRYPTED_EXPECTED			= 10;
const ERROR_ENCRYPTED_UNEXPECTED		= 11;
const ERROR_UNKNOWN_SYS_MSG				= 12;
const ERROR_MISMATCHED_ANSWER			= 13;
const ERROR_SERVER_ERROR				= 14;
const ERROR_SERVICE_UNKNWON				= 15;
const ERROR_DECRYPT_FAILED				= 16;
const ERROR_PROTOCOL_TOO_NEW			= 17;
const ERROR_UNIXSOCK_WRONGPATH			= 18;
const ERROR_WRONG_HANDLER				= 19;
const ERROR_SEND_SOCKET					= 20;
const ERROR_CONTEXT_CREATION			= 21;
const ERROR_ALREADY_STARTED				= 22;
const ERROR_SERVER_SOCKET				= 24;
const ERROR_CONTROL_INTERFACE			= 25;
const ERROR_UNIXSOCK_EXISTS				= 26;
const ERROR_CONTROL_SOCKET				= 27;
const ERROR_SERVER_STREAM_SELECT		= 28;
const ERROR_SERVER_ACCEPT				= 29;
const ERROR_SERVICE_MISSING_DEST		= 30;
const ERROR_CLIENT_HANDSHAKE			= 31;
const ERROR_CLIENT_FRAME				= 32;
const ERROR_CLIENT_PAYLOAD_SIZE			= 33;
const ERROR_CLIENT_WRONG_OPCODE			= 34;
const ERROR_COMMAND_TYPE				= 35;
const ERROR_COMMAND_PARAMETERS			= 36;
const ERROR_MESSAGE_FORMAT				= 37;
const ERROR_CONTROL_PROTOCOL			= 38;
const ERROR_CONTROL_COMMAND_UNKNOWN		= 39;
const ERROR_UNKNOWN_CLIENT				= 40;
const ERROR_CONTROL_ACCEPT				= 41;
const ERROR_UNKNOWN_SOCKET				= 42;
const ERROR_CLIENT_INACTIVE				= 43;
const ERROR_SERVICE_NOT_FOUND			= 100;
const ERROR_MISSING_PARAM				= 101;

const FLAG_ENCRYPTED					= 0x01;
const FLAG_COMMAND						= 0x02;

const COMMAND_AUTH						= 1000; /* 0-999 are reserved as system messages */
const COMMAND_SELECT_SERVICE			= 1001;
const COMMAND_HELO						= 1002;
const COMMAND_CUSTOM_CMD				= 1003;
const COMMAND_LIST_CLIENTS				= 1004;
const COMMAND_SEND_MESSAGE				= 1005;

const SOCK_TYPE_SERVER					= 1;
const SOCK_TYPE_CLIENT					= 2;
const SOCK_TYPE_CONTROL_INTERFACE			= 3;
const SOCK_TYPE_CONTROL_CLIENT			= 4;

const PROT_OPCODE_CONTINUATION			= 0x0;
const PROT_OPCODE_TEXT					= 0x1;
const PROT_OPCODE_BINARY				= 0x2;
const PROT_OPCODE_CONN_CLOSE			= 0x8;
const PROT_OPCODE_PING					= 0x9;
const PROT_OPCODE_PONG					= 0xA;
const PROT_OPCODE_RESERVED				= 0xFF; // impossible value, opcode is 4bit

const PROT_OPCODE_DESCR = [
	PROT_OPCODE_CONTINUATION			=> "Continuation",
	PROT_OPCODE_TEXT					=> "Text",
	PROT_OPCODE_BINARY					=> "Binary",
	PROT_OPCODE_CONN_CLOSE				=> "Connection Close",
	PROT_OPCODE_PING					=> "Ping",
	PROT_OPCODE_PONG					=> "Pong",
	PROT_OPCODE_RESERVED				=> "Unknown/Reserved"
];

const PROT_CLOSE_CODE_NORMAL			= 1000;
const PROT_CLOSE_CODE_AWAY				= 1001;
const PROT_CLOSE_PROT_ERROR				= 1002;
const PROT_CLOSE_UNSUPP_DATA			= 1003;
const PROT_CLOSE_RESERVED				= 1004;
const PROT_CLOSE_NO_STATUS				= 1005;
const PROT_CLOSE_ABNORM_CLOSE			= 1006;
const PROT_CLOSE_INV_FRAME_PAYLOAD		= 1007;
const PROT_CLOSE_POLICY_VIOLATION		= 1008;
const PROT_CLOSE_MESSAGE_TOO_BIG		= 1009;
const PROT_CLOSE_MANDATORY_EXT			= 1010;
const PROT_CLOSE_INTERNAL_SERVER_ERR	= 1011;
const PROT_CLOSE_TLS_HANDSHAKE			= 1015;

const PROT_CLOSE_CODE_DESCR = [
	PROT_CLOSE_CODE_NORMAL				=> "Normal Closure",
	PROT_CLOSE_CODE_AWAY				=> "Going Away",
	PROT_CLOSE_PROT_ERROR				=> "Protocol error",
	PROT_CLOSE_UNSUPP_DATA				=> "Unsupported Data",
	PROT_CLOSE_RESERVED					=> "Reserved",
	PROT_CLOSE_NO_STATUS				=> "No Status Rcvd",
	PROT_CLOSE_ABNORM_CLOSE				=> "Abnormal Closure",
	PROT_CLOSE_INV_FRAME_PAYLOAD		=> "Invalid frame payload data",
	PROT_CLOSE_POLICY_VIOLATION			=> "Policy Violation",
	PROT_CLOSE_MESSAGE_TOO_BIG			=> "Message Too Big",
	PROT_CLOSE_MANDATORY_EXT			=> "Mandatory Ext.",
	PROT_CLOSE_INTERNAL_SERVER_ERR		=> "Internal Server Error",
	PROT_CLOSE_TLS_HANDSHAKE			=> "TLS handshake",
];
