<?php
/**
 * Event
 * 
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link http://www.formsphpframework.com
 */

namespace FF\Core\Common\Events;

/**
 * La classe che definisce il singolo oggetto evento. Non è necessario istanziarla direttamente, viene utilizzata direttamente la funzione addEvent()
 * presente negli oggetti che supportano la gestione degli eventi.
 * Non è altresì necessario richiamare direttamente un qualsiasi metodo.
 *
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link http://www.formsphpframework.com
 */
class Queue
{
	use Events;
}
