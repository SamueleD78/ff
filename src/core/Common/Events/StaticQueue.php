<?php
/**
 * Event Events
 *
 * @package FormsFramework
 * @subpackage base
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link http://www.formsphpframework.com
 */

namespace FF\Core\Common\Events;

/**
 * Questa classe rappresenta un "fantoccio" utilizzato per creare una coda eventi "neutra", cioè non associata a nessun oggetto.
 * Non ha contenuto in quanto sono sufficienti i contenuti di ffCommon, che eredita
 *
 * @package FormsFramework
 * @subpackage base
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link http://www.formsphpframework.com
 */
trait StaticQueue
{
	/**
	 * Questa variabile memorizza la coda standard degli eventi, utilizzata dalle funzioni di gestione medesime.
	 *
	 * @var $_events
	 */
	static protected $_events = null;

	static public function addEvent($event_name, $func_name, $priority = null, $index = 0, $break_when = null, $break_value = null, $additional_data = null)
	{
		self::initEvents();
		self::$_events->addEvent($event_name, $func_name, $priority, $index, $break_when, $break_value, $additional_data);
	}

	static public function doEvent($event_name, $event_params = array())
	{
		self::initEvents();
		return self::$_events->doEvent($event_name, $event_params);
	}

	static private function initEvents()
	{
		if (self::$_events === null)
			self::$_events = new Queue();
	}
}
