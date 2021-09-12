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
class Event
{
	/**
	 * Questa costante definisce che l'evento non può interrompere la coda eventi. E' il default.
	 */
	const BREAK_NEVER 		= 0;
	/**
	 * Questa costante definisce che l'evento interrompe la coda eventi quando il valore di ritorno è uguale a $break_value
	 */
	const BREAK_EQUAL 		= 1;
	/**
	 * Questa costante definisce che l'evento interrompe la coda eventi quando il valore di ritorno è diverso da $break_value
	 */
	const BREAK_NOT_EQUAL 	= 2;
	/**
	 * Questa costante definisce che l'evento interrompe la coda eventi quando il valore di ritorno della callback indicata da $break_value è uguale a true
	 */
	const BREAK_CALLBACK 	= 3;
	const BREAK_DEFAULT 	= Event::BREAK_NEVER;
	
	/**
	 * la priorità massima nella coda eventi. non viene usata da nessun oggetto o funzione d'architettura.
	 */
	const PRIORITY_TOPLEVEL = 0;
	/**
	 * la priorità alta nella coda eventi
	 */
	const PRIORITY_HIGH		= 1;
	/**
	 * la priorità normale nella coda eventi. è il default.
	 */
	const PRIORITY_NORMAL 	= 2;
	/**
	 * la priorità bassa nella coda eventi
	 */
	const PRIORITY_LOW		= 3;
	/**
	 * la priorità più bassa nella coda eventi. non viene usata da nessun oggetto o funzione d'architettura.
	 */
	const PRIORITY_FINAL 	= 4;
	const PRIORITY_DEFAULT 	= Event::PRIORITY_NORMAL;
	
	public $func_name;
	public $break_when;
	public $break_value;
	public $additional_data;
	
	/**
	 *
	 * @param callable|string $func_name il nome della funzione da eseguire nell'evento
	 * @param Event::BREAK_* $break_when Che tipo di controllo applicare a $break_value
	 * @param Mixed $break_value il valore utilizzato per effettuare il check di rottura della catena
	 * @param Mixed $additional_data dati aggiuntivi da passare fra gli eventi
	 */
	public function __construct(callable|string $func_name, $break_when, $break_value, $additional_data)
	{
		$this->func_name = $func_name;
		$this->break_when = $break_when;
		$this->break_value = $break_value;
		$this->additional_data = $additional_data;
	}
	
	/**
	 * Verifica se è necessario interrompere la catena degli eventi basandosi sul risultato dell'evento
	 * @param Mixed $result Il risultato dell'evento
	 * @return Boolean
	 */
	public function checkBreak($result)
	{
		switch($this->break_when)
		{
			case Event::BREAK_CALLBACK:
				return call_user_func($this->break_value, $result);
				
			case Event::BREAK_EQUAL:
				if ($result === $this->break_value)
					return true;
				break;
				
			case Event::BREAK_NOT_EQUAL:
				if ($result !== $this->break_value)
					return true;
				break;
				
		}
		return false;
	}
}
