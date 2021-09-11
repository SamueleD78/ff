<?php
/**
 * Event Events
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
trait Events
{
	var array $ff_events 		= array();

	/**
	 * Questa funzione permette di aggiungere un evento alla coda dei medesimi.
	 *
	 * @param string $event_name il nome dell'evento
	 * @param string|callable $func_name il nome della funzione da richiamare
	 * @param int|null $priority la priorità dell'evento. Può essere un qualsiasi valore di Event::PRIORITY_*
	 * @param int $index la posizione dell'evento rispetto ad eventi della stessa priorità
	 * @param int|null $break_when se il processing della coda dev'essere interrotto sulla base di $break_value. Può essere un qualsiasi valore di Event::BREAK_*
	 * @param mixed $break_value il valore da utilizzare in coppia con $break_when
	 * @param mixed $additional_data eventuali dati addizionali da passare insieme all'evento
	 * @return Events
	 */
	public function addEvent(string $event_name, string|callable $func_name, ?int $priority = null, int $index = 0, ?int $break_when = null, mixed $break_value = null, mixed $additional_data = null): static
	{
		if (is_array($func_name))
		{
			$data = $func_name;
			$func_name			= $data["func_name"];
			$priority			= $data["priority"];
			$index				= $data["index"] === null ? 0 : $data["index"];
			$break_when			= $data["break_when"];
			$break_value		= $data["break_value"];
			$additional_data	= $data["additional_data"];
		}

		if ($priority === null)
		{
			if (isset($this->ff_events[$event_name]["defaults"]))
			{
				$priority = $this->ff_events[$event_name]["defaults"]["priority"];
			}
			else
				$priority = Event::PRIORITY_DEFAULT;
		}

		if ($break_when === null)
		{
			if (isset($this->ff_events[$event_name]["defaults"]))
			{
				$break_when = $this->ff_events[$event_name]["defaults"]["break_when"];
			}
		}

		if ($break_when !== null && $break_value === null)
		{
			if (isset($this->ff_events[$event_name]["defaults"]))
			{
				$break_value = $this->ff_events[$event_name]["defaults"]["break_value"];
			}
		}

		if ($index === null)
			$index = 0;

		$event = new Event($func_name, $break_when, $break_value, $additional_data);

		switch ($priority)
		{
			case Event::PRIORITY_TOPLEVEL:
				if (isset($this->ff_events[$event_name]["toplevel"]))
					throw new \Exception("A toplevel event already exists");

				$this->ff_events[$event_name]["toplevel"] = $event;
				break;

			case Event::PRIORITY_FINAL:
				if (isset($this->ff_events[$event_name]["final"]))
					throw new \Exception("A final event already exists");

				$this->ff_events[$event_name]["final"] = $event;
				break;

			default:
				$this->ff_events[$event_name]["queues"][$priority][] = array("index" => $index, "counter" => count($this->ff_events[$event_name]["queues"][$priority]), "event" => $event);
				break;
		}

		return $this;
	}

	/**
	 * Questa funzione esegue tutte le code per l'evento selezionato.
	 *
	 * @param string $event_name il nome dell'evento da eseguire
	 * @param array $event_params i parametri dell'evento passati all'interno di un array. Il numero e il tipo di parametri dipendono dall'evento.
	 * @return null[] $mixed un array contenente i risultati di ogni funzione eseguita
	 */
	public function doEvent(string $event_name, array $event_params = array()): array
	{
		$results = array(null);
		if (defined("FF_EVENTS_STOP"))
			return $results;

		if (isset($this->ff_events[$event_name]))
		{
			if (isset($this->ff_events[$event_name]["toplevel"]))
			{
				$event = $this->ff_events[$event_name]["toplevel"];
				if (is_array($event->additional_data))
					$calling_params = array_merge($event_params, $event->additional_data);
				else
					$calling_params = $event_params;
				if (is_string($event->func_name))
					$event_key = $event->func_name;
				else
					$event_key = "__toplevel__";
				$results[$event_key] = call_user_func_array($event->func_name, $calling_params);

				if ($event->checkBreak($results[$event_key]))
					return $results;
			}

			if (isset($this->ff_events[$event_name]["queues"]) && is_array($this->ff_events[$event_name]["queues"]) && count($this->ff_events[$event_name]["queues"]))
			{
				ksort($this->ff_events[$event_name]["queues"], SORT_NUMERIC);
				foreach ($this->ff_events[$event_name]["queues"] as $key => $value)
				{
					if (is_array($value) && count($value))
					{
						usort($value, "ffCommon_IndexReverseOrder");

						foreach ($value as $subkey => $subvalue)
						{
							if (is_array($subvalue["event"]->additional_data))
								$calling_params = array_merge($event_params, $subvalue["event"]->additional_data);
							else
								$calling_params = $event_params;
							$calling_params["__last_result__"] = end($results);
							if (is_string($subvalue["event"]->func_name))
								$event_key = $subvalue["event"]->func_name;
							else
								$event_key = $key;

							if (!is_callable($subvalue["event"]->func_name))
								throw new \Exception("Wrong Event Params");

							$results[$event_key] = call_user_func_array($subvalue["event"]->func_name, $calling_params);

							if($subvalue["event"]->checkBreak($results[$event_key]))
								return $results;
						}
						reset($value);
					}
				}
				reset($this->ff_events[$event_name]["queues"]);
			}

			if (isset($this->ff_events[$event_name]["final"]))
			{
				$event = $this->ff_events[$event_name]["final"];
				if (is_array($event->additional_data))
					$calling_params = array_merge($event_params, $event->additional_data);
				else
					$calling_params = $event_params;
				if (is_string($event->func_name))
					$event_key = $event->func_name;
				else
					$event_key = "__final__";
				$calling_params["__last_result__"] = end($results);
				$results[$event_key] = call_user_func_array($event->func_name, $calling_params);
			}
		}

		return $results;
	}
}