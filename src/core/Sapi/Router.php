<?php
/**
 * @package FormsFramework
 * @subpackage core\Sapi
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://ffphp.com
 */

namespace FF\Core\Sapi;

use FF\Core\Common;

/**
 * @package FormsFramework
 * @subpackage SAPI
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://ffphp.com
 */
class Router
{
	const PRIORITY_TOP 			= 0;
	const PRIORITY_VERY_HIGH	= 1;
	const PRIORITY_HIGH			= 2;
	const PRIORITY_NORMAL 		= 3;
	const PRIORITY_LOW			= 4;
	const PRIORITY_VERY_LOW		= 5;
	const PRIORITY_BOTTOM 		= 6;
	const PRIORITY_DEFAULT 		= self::PRIORITY_NORMAL;
	
	public $rules 			= array();
	public $named_rules 	= array();
	public $counter			= 0;
	
	public $ordered			= false;
	
	public function getRuleById($id)
	{
		if (isset($this->named_rules[$id]))
			return $this->named_rules[$id];
		else
			return null;
	}

	/**
	 *
	 * @param string $source
	 * @param string|array $destination
	 * @param int $priority
	 * @param bool $accept_path_info
	 * @param bool $process_next
	 * @param int $index
	 * @param array $attrs
	 * @param string|null $reverse
	 * @param string|null $query
	 * @param string|null $useragent
	 * @param bool $blocking
	 * @param bool $control (cache)
	 * @param array $hosts
	 */
	public function addRule(string $source, string|array $destination, int $priority = self::PRIORITY_DEFAULT, bool $accept_path_info = false, bool $process_next = false, int $index = 0, array $attrs = array(), ?string $reverse = null, ?string $query = null, ?string $useragent = null, bool $blocking = false, bool $control = false, array $hosts = array())
	{
		// --------------------------------------------
		// CREAZIONE REGOLA
		
		$rule = new \SimpleXMLElement("<rule></rule>");
		
		$rule->addChild("source", $source);
		
		if (is_array($destination) && count($destination))
		{
			$tmp_dest = $rule->addChild("destination");
			foreach ($destination as $key => $value)
			{
				$tmp_dest->addChild($key, $value);
			}
			reset($destination);
		}
		else
			$rule->addChild("destination", $destination);
		
		switch ($priority)
		{
			case self::PRIORITY_TOP:
				$rule->addChild("priority", "TOP");
				break;

			case self::PRIORITY_VERY_HIGH:
				$rule->addChild("priority", "VERY_HIGH");
				break;

			case self::PRIORITY_HIGH:
				$rule->addChild("priority", "HIGH");
				break;

			case self::PRIORITY_LOW:
				$rule->addChild("priority", "LOW");
				break;

			case self::PRIORITY_VERY_LOW:
				$rule->addChild("priority", "VERY_LOW");
				break;

			case self::PRIORITY_BOTTOM:
				$rule->addChild("priority", "BOTTOM");
				break;

			default:
				$rule->addChild("priority", "NORMAL");
		}
		
		if ($accept_path_info)
			$rule->addChild("accept_path_info");

		if ($process_next)
			$rule->addChild("process_next");
		
		$rule->addChild("index", $index);
		
		if (is_array($attrs) && count($attrs))
		{
			foreach ($attrs as $key => $value)
			{
				$rule->addAttribute($key, $value);
			}
			reset($attrs);
		}
		
		if (is_array($hosts) && count($hosts))
		{
			foreach ($hosts as $value)
			{
				$rule->addChild("host", $value);
			}
			reset($attrs);
		}
		
		if ($reverse)
			$rule->addChild("reverse", $reverse);

		if ($query !== null)
			$rule->addChild("query", $query);
		
		if (is_array($useragent) && count($useragent))
		{
			$tmp_usrag = $rule->addChild("useragent");
			foreach ($useragent as $key => $value)
			{
				$tmp_usrag->addChild($key, $value);
			}
			reset($useragent);
		}
		
		if ($blocking)
			$rule->addChild("blocking");

		if ($control)
			$rule->addChild("control");
		
		// FINE CREAZIONE REGOLA
		// --------------------------------------------

		$this->addElementRule($rule);
	}
	
	public function addXMLRule($xml)
	{
		// --------------------------------------------
		// CREAZIONE REGOLA
		
		$rule = new \SimpleXMLElement($xml);
		
		// FINE CREAZIONE REGOLA
		// --------------------------------------------

		$this->addElementRule($rule);
	}
	
	public function loadFile($file)
	{
		$xml = new \SimpleXMLElement("file://" . $file, null, true);
		
		if (count($xml->rule))
		{
			foreach ($xml->rule as $key => $rule)
			{
				if ($key == "comment")
					continue;
				
				$this->addElementRule($rule);
			}
		}
		return;
	}
	
	private function addElementRule($rule)
	{
		$this->ordered = false;
		
		$this->counter++;
		$rule->counter = $this->counter;

		$attrs = $rule->attributes();
		
		// check required params
		if (isset($rule->priority))
			$priority = (string)$rule->priority;
		else
			$priority = "NORMAL";

		if (!isset($rule->index))
			$rule->addChild("index", "0");

		// convert object, cache or not
		$rule = new Common\Serializable($rule);

		// populate queues
		if (isset($attrs["id"]))
			$this->named_rules[(string)$attrs["id"]] = $rule;
		
		switch (strtoupper($priority))
		{
			case "TOP":
				$this->rules[self::PRIORITY_TOP][] = $rule;
				break;

			case "VERY_HIGH":
				$this->rules[self::PRIORITY_VERY_HIGH][] = $rule;
				break;

			case "HIGH":
				$this->rules[self::PRIORITY_HIGH][] = $rule;
				break;

			case "LOW":
				$this->rules[self::PRIORITY_LOW][] = $rule;
				break;

			case "VERY_LOW":
				$this->rules[self::PRIORITY_VERY_LOW][] = $rule;
				break;

			case "BOTTOM":
				$this->rules[self::PRIORITY_BOTTOM][] = $rule;
				break;

			default:
				$this->rules[self::PRIORITY_DEFAULT][] = $rule;
		}
	}
	
	public function orderRules($priority = null)
	{
		if ($priority)
		{
			if (!isset($this->rules[$priority]))
				return;

			usort($this->rules[$priority], "\FF\Common\IndexOrder");
			$this->rules[$priority] = array_reverse($this->rules[$priority]);
		}
		else
		{
			for($i = self::PRIORITY_TOP; $i <= self::PRIORITY_BOTTOM; $i++)
			{
				if (!isset($this->rules[$i]))
					continue;

				usort($this->rules[$i], "\FF\Common\IndexOrder");
				$this->rules[$i] = array_reverse($this->rules[$i]);
			}
			
			$this->ordered = true;
		}
	}
	
	public function process($url, $query = null, $host = null)
	{
		$matched_rules = array();

		for($i = self::PRIORITY_TOP; $i <= self::PRIORITY_BOTTOM; $i++)
		{
			if (!isset($this->rules[$i]))
				continue;

			if (!$this->ordered)
				$this->orderRules($i);
				
			foreach ($this->rules[$i] as $key => $value)
			{
				$host_matches = null;
				
				$attrs = $value->__attributes; //self::getRuleAttrs($value);
				
				if ($host !== null && isset($value->host))
				{
					if (!isset($attrs["host_mode"]) || strtolower($attrs["host_mode"]) == "allow")
						$host_allow = false;
					if (strtolower($attrs["host_mode"]) == "disallow")
						$host_allow = true;
					
					if (count($value->host) == 1)
					{
						$host_matches = array();
						$host_rc = preg_match('/' . str_replace('/', '\/', $value->host) . '/', $host, $host_matches,  PREG_OFFSET_CAPTURE);
						if ($host_rc)
						{
							if (!isset($attrs["host_mode"]) || strtolower($attrs["host_mode"]) == "allow")
								$host_allow |= true;
							elseif (strtolower($attrs["host_mode"]) == "disallow")
								$host_allow &= false;
						}
					}
					else
					{
						for($c = 0; $c < count($value->host); $c++)
						{
							$host_matches = array();
							$host_rc = preg_match('/' . str_replace('/', '\/', $value->host[$c]) . '/', $host, $host_matches,  PREG_OFFSET_CAPTURE);

							if ($host_rc)
							{
								if (!isset($attrs["host_mode"]) || strtolower($attrs["host_mode"]) == "allow")
									$host_allow |= true;
								elseif (strtolower($attrs["host_mode"]) == "disallow")
									$host_allow &= false;
							}
						}
					}
					
					if (!$host_allow)
						continue;
				}
				
				if (!is_array($value->source))
				{
					$matches = array();
					$rc = preg_match('/' . str_replace('/', '\/', $value->source) . '/', $url, $matches,  PREG_OFFSET_CAPTURE);
					if($rc && isset($value->query) && strlen($value->query) && strlen($query))
						$rc = preg_match('/' . str_replace('/', '\/', $value->query) . '/', $query, $matches,  PREG_OFFSET_CAPTURE);
				}
				else
				{
					$rc = false;
					
					for($c = 0; $c < count($value->source); $c++)
					{
						$matches = array();
						$sub_rc = preg_match('/' . str_replace('/', '\/', $value->source[$c]) . '/', $url, $matches,  PREG_OFFSET_CAPTURE);
						if($sub_rc && isset($value->query[$c]) && strlen($value->query[$c]) && strlen($query))
							$sub_rc = preg_match('/' . str_replace('/', '\/', $value->query[$c]) . '/', $query, $matches,  PREG_OFFSET_CAPTURE);
						$rc |= $sub_rc;
					}
				}
				
				if ($rc)
				{
					if (isset($attrs["id"]) && strlen((string)$attrs["id"]))
						$matched_rules[(string)$attrs["id"]] = array("rule" => $value, "params" => $matches);
					else
						$matched_rules[] = array("rule" => $value, "params" => $matches, "host_params" => $host_matches);
				}
			}
			reset($this->rules[$i]);
		}
		
		$this->ordered = true;
		
		return $matched_rules;
	}
	
	static function getRuleAttrs($rule)
	{
		if (get_class($rule) == "FF\Common\Serializable")
			return $rule->__attributes;
		else
			return $rule->attributes();

	}
}