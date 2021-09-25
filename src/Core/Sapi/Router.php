<?php
/**
 * This file is part of FF: Forms PHP Framework
 *
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://ffphp.com
 */

namespace FF\Core\Sapi;

use SimpleXMLElement;

/**
 * maps a source to urls depending on the rules set
 */
class Router
{
	/**
	 * @var array<string, array<string, Rule>>
	 */
	public array $rules 		= array();
	/**
	 * @var array<string, Rule>
	 */
	public array $rules_by_id 	= array();

	public bool $ordered		= false;
	
	public function getRuleById(string $id): ?Rule
	{
		return $this->rules_by_id[$id] ?? null;
	}

	/**
	 * @throws \Exception
	 */
	public function addXMLRule(string $xml)
	{
		$this->newFromSimpleXML(new SimpleXMLElement($xml));
	}

	/**
	 * @throws \Exception
	 */
	public function loadFile(string $file): void
	{
		$xml = new SimpleXMLElement("file://" . $file, null, true);
		
		if (count($xml->rule))
		{
			foreach ($xml->rule as $key => $rule)
			{
				if ($key === "comment")
					continue;

				$this->newFromSimpleXML($rule);
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	public function newFromSimpleXML(SimpleXMLElement $obj): static
	{
		$newRule = new Rule();

		if (isset($obj->priority))
		{
			if (!isset(PRIORITY::_REVERSE_DESCR[(string)$obj->priority]))
				throw new \Exception("Unhandled priority type: " . $obj->priority);

			$newRule->setPriority(PRIORITY::_REVERSE_DESCR[(string)$obj->priority]);
		}

		if (isset($obj->sources) && is_array($obj->sources))
		{
			foreach ($obj->sources as $source)
			{
				$newRule->setSource(path: (string)$source);
			}
		}
		else if (isset($obj->source))
			$newRule->setSource(path: (string)$obj->source);
		else
			throw new \Exception("source missing");

		if (isset($obj->query))
			$newRule->setQuery((string)$obj->query);

		if (isset($obj->useragent))
			$newRule->setUseragent((string)$obj->useragent);

		if (isset($obj->hosts) && is_array($obj->hosts))
		{
			foreach ($obj->hosts as $host)
			{
				$newRule->setHost(value: (string)$host);
			}
		}
		else if (isset($obj->host))
			$newRule->setHost(value: (string)$obj->host);

		if (isset($obj->host_mode_disallow))
			$newRule->setHostModeDisallow(true);

		if (isset($obj->accept_path_info))
			$newRule->setAcceptPathInfo(true);

		if (isset($obj->process_next))
			$newRule->setProcessNext(true);

		if (isset($obj->destinations) && is_array($obj->destinations))
		{
			foreach ($obj->destinations as $key => $value)
			{
				$newRule->setDestination(key: $key, path: (string)$value);
			}
		}
		else if (isset($obj->destination))
			$newRule->setDestination(path: (string)$obj->destination);
		else
			throw new \Exception("Destination missing");

		if (isset($obj->reverse))
			$newRule->setReverse((string)$obj->reverse);

		$attrs = $obj->attributes();
		if (is_array($attrs) && count($attrs))
		{
			foreach ($attrs as $key => $value)
			{
				$newRule->addAttr($key, $value);
			}
		}

		return $this->addRule($newRule);
	}

	private function addRule(Rule $rule): static
	{
		$this->ordered = false;
		
		// populate queues
		$this->rules_by_id[$rule->getID()] = $rule;
		$this->rules[$rule->getPriorityDesc()][$rule->getID()] = $rule;

		return $this;
	}

	/**
	 * @param null $priority
	 * @return $this
	 */
	public function orderRules($priority = null): static
	{
		if ($priority)
		{
			if (!isset($this->rules[$priority]))
				return $this;

			usort($this->rules[$priority], "\FF\Core\Common\IndexOrder");
			$this->rules[$priority] = array_reverse($this->rules[$priority]);
		}
		else
		{
			foreach ($this->rules as $key => $whocares)
			{
				usort($this->rules[$key], "\FF\Core\Common\IndexOrder");
				$this->rules[$key] = array_reverse($this->rules[$key]);
			}
			$this->ordered = true;
		}

		return $this;
	}

	/**
	 * @param string $url
	 * @param string|null $query
	 * @param string|null $host
	 * @return MatchedRules
	 */
	public function process(string $url, ?string $query = null, ?string $host = null): MatchedRules
	{
		$matched_rules = array();

		for($i = PRIORITY::TOP; $i <= PRIORITY::BOTTOM; $i++)
		{
			$queue_key = PRIORITY::_DESCR[$i];

			if (!isset($this->rules[$queue_key]))
				continue;
			
			if (!$this->ordered)
				$this->orderRules($queue_key);
				
			foreach ($this->rules[$queue_key] as $rule)
			{
				$host_matches = null;
				
				if ($host !== null && $rule->hasHosts())
				{
					$host_allow = $rule->isHostModeDisallow();

					foreach ($rule->getHosts() as $value)
					{
						$host_matches = array();
						$host_rc = preg_match('/' . str_replace('/', '\/', $value) . '/', $host, $host_matches,  PREG_OFFSET_CAPTURE);

						if ($host_rc)
						{
							if (!$rule->isHostModeDisallow())
								$host_allow |= true;
							else
								$host_allow &= false;
						}
					}

					if (!$host_allow)
						continue;
				}
				
				foreach ($rule->getSources() as $value)
				{
					$matches = array();
					$sub_rc = preg_match('/' . str_replace('/', '\/', $value) . '/', $url, $matches,  PREG_OFFSET_CAPTURE);
					if ($sub_rc && strlen($query) && $rule->getQuery())
						$sub_rc = preg_match('/' . str_replace('/', '\/', $rule->getQuery()) . '/', $query, $matches,  PREG_OFFSET_CAPTURE);
					if ($sub_rc)
					{
						$matched_rules[$rule->getID()] = new MatchedRule(
							rule: $rule,
							source: $value,
							params: $matches,
							host_params: $host_matches
						);
						break;
					}
				}
			}
		}
		
		$this->ordered = true;
		
		return new MatchedRules($matched_rules);
	}
}