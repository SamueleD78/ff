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
 * namespace emulation
 *
 * Questa classe simula l'utilizzo dei namespace.
 * Non può essere istanziata direttamente, è necessario usare il metodo getInstance()
 *
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://www.ffphp.com
 */
class Globals
{
	private static ?array $instances = null;

    /*public function __call($method, $args)
    {
        if (isset($this->$method)) {
            $func = $this->$method;
            return call_user_func_array($func, $args);
        }
    }*/

	private function __construct()
	{
	}

	/**
	 * Questa funzione restituisce un "finto" namespace sotto forma di oggetto attraverso il quale è possibile definire
	 * variabili ed oggetti in modo implicito (magic).
	 * 
	 * @param string|null $namespace il nome del namespace desiderato. Se omesso è "default"
	 * @return Globals
	 */
	public static function getInstance(string $namespace = null): Globals
	{
		if ($namespace == null)
			$namespace = "default";
		
		if (Globals::$instances === null)
			Globals::$instances = array();
		
		if (!isset(Globals::$instances[$namespace]))
			Globals::$instances[$namespace] = new Globals();
			
		return Globals::$instances[$namespace];
	}
}