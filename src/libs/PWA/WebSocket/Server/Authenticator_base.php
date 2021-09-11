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

abstract class Authenticator_base
{
	abstract function authenticate(array $payload, ControlClient_base $client): bool;
	abstract function authorizeService(string|null $service_name, ControlClient_base $client): bool;
}
