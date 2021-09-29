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

/**
 * Questa classe astratta implementa i check per i magic methods, in modo da impedire la creazione di proprietà o metodi non precedentemente definiti.
 *
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://www.ffphp.com
 */
trait ClassChecks
{
	public function __set ($name, $value)
	{
		ErrorHandler::raise("property \"$name\" not found on class " . static::class, E_USER_ERROR, $this, get_defined_vars());
	}

	public function __get ($name)
	{
		ErrorHandler::raise("property \"$name\" not found on class " . static::class, E_USER_ERROR, $this, get_defined_vars());
	}

	public function __isset ($name)
	{
		ErrorHandler::raise("property \"$name\" not found on class " . static::class, E_USER_ERROR, $this, get_defined_vars());
	}

	public function __unset ($name)
	{
		ErrorHandler::raise("property \"$name\" not found on class "  . static::class, E_USER_ERROR, $this, get_defined_vars());
	}

	public function __call ($name, $arguments)
	{
		ErrorHandler::raise("function \"$name\" not found on class "  . static::class, E_USER_ERROR, $this, get_defined_vars());
	}

	public static function __callStatic ($name, $arguments)
	{
		ErrorHandler::raise("function \"$name\" not found on class "  . static::class, E_USER_ERROR, null, get_defined_vars());
	}
}
