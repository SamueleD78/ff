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

use FF\Core\Common\Orderable;
use JetBrains\PhpStorm\ExpectedValues;
use function FF\Core\Common\uuidv4;

class Rule
{
	use Orderable;

	/**
	 * the source path, without the host section. It could include regexp expressions.
	 * grouped expressions will be extracted as params when matched
	 * e.g.: /article/(\d+) will expect a number combination, and that will be extracted
	 * as param #1 when the rule will match
	 * @var int
	 */
	#[ExpectedValues(valuesFromClass: PRIORITY::class)]
	protected int 				$priority 			= PRIORITY::DEFAULT;

	/**
	 * the source path, without the host section. It could include regexp expressions.
	 * grouped expressions will be extracted as params when matched
	 * e.g.: /article/(\d+) will expect a number combination, and that will be extracted
	 * as param #1 when the rule will match
	 * @var array<string, string>
	 */
	protected array $sources 						= array();

	/**
	 * a query string to be associated with the url
	 * @var string|null
	 */
	protected ?string      		$query 				= null;

	/**
	 * limits the mapping to a source useragent
	 * @var string|null
	 */
	protected ?string      		$useragent 			= null;

	/**
	 * a set of hosts to be used in conjunction with the attribute host_mode
	 * @var array
	 */
	protected array        		$hosts 				= array();

	/**
	 * by default, the hosts present in $hosts are the allowed one. Enabling this will make them the disallowed ones
	 * @var bool
	 */
	protected bool				$host_mode_disallow = false;

	/**
	 * if enabled, the path could be matched with partial correspondences
	 * for instance, a source path "/article/" will match "article/1", "article/2", etc.
	 * @var bool
	 */
	protected bool         		$accept_path_info 	= false;

	/**
	 * if enabled, when a rule will match it will not stop the matching process,
	 * allowing for multiple rule matching. That's useful when a single path needs to be
	 * mapped with different functions (as it happens for authentication systems covering
	 * multiple urls)
	 * @var bool
	 */
	protected bool         		$process_next 		= false;

	/**
	 * the destination to be mapped. It could be a single string or a set of values.
	 * the interpretation of this param is left to the software using the class
	 * @var array
	 */
	protected array $destination					= array();

	/**
	 * a path to be associated with the rule, ready to be retrieved when needed.
	 * This is useful to have a single url repository to be used with the code
	 * @var string|null
	 */
	protected ?string      		$reverse 			= null;

	/**
	 * extra attributes to be associated with a rule. One common attribute is "id", used
	 * to name and recover rules
	 * @var array
	 */
	protected array        		$attrs = array();

	/*******************************************************************************************************************
	 * functions
	 * @throws \Exception
	 */

	public function __construct(?string $id = null)
	{
		$this->setCounter();
		$this->addAttr("id", $id ?? uuidv4());
	}

	/**
	 * The rule ID. Helps with recovering the rule inside the router
	 * @param string $id
	 * @return $this
	 */
	private function setID(string $id): static
	{
		$this->addAttr("id", $id);
		return $this;
	}

	/**
	 * See {@see setID}
	 * @return string
	 */
	public function getID(): string
	{
		return $this->getAttr("id");
	}

	/**
	 * See {@see $attrs}
	 * @return array
	 */
	public function getAttrs(): array
	{
		return $this->attrs;
	}

	/**
	 * See {@see $attrs}
	 * @param string $key
	 * @return string
	 */
	public function getAttr(string $key): string
	{
		return $this->attrs[$key];
	}

	/**
	 * See {@see $attrs}
	 * @param string $key
	 * @param string $value
	 * @return $this
	 */
	public function addAttr(string $key, string $value): static
	{
		$this->attrs[$key] = $value;
		return $this;
	}

	/**
	 * See {@see $attrs}
	 * @param string $key
	 * @return $this
	 */
	public function delAttr(string $key): static
	{
		unset($this->attrs[$key]);
		return $this;
	}

	/**
	 * See {@see $query}
	 * @return string|null
	 */
	public function getQuery(): ?string
	{
		return $this->query;
	}

	/**
	 * See {@see $query}
	 * @param string|null $query
	 * @return Rule
	 */
	public function setQuery(?string $query): static
	{
		$this->query = $query;
		return $this;
	}

	/**
	 * See {@see $useragent}
	 * @return string|null
	 */
	public function getUseragent(): ?string
	{
		return $this->useragent;
	}

	/**
	 * See {@see $useragent}
	 * @param string|null $useragent
	 * @return Rule
	 */
	public function setUseragent(?string $useragent): static
	{
		$this->useragent = $useragent;
		return $this;
	}

	/**
	 * See {@see $hosts}
	 * @return array
	 */
	public function getHosts(): array
	{
		return $this->hosts;
	}

	/**
	 * See {@see $hosts}
	 * @return bool
	 */
	public function hasHosts(): bool
	{
		return count($this->hosts) > 0;
	}

	/**
	 * See {@see $hosts}
	 * @param string $key
	 * @return string
	 */
	public function getHost(string $key): string
	{
		return $this->hosts[$key];
	}

	/**
	 * See {@see $hosts}
	 * @param string|null $key
	 * @param string|null $value
	 * @return Rule
	 * @throws \Exception
	 */
	public function setHost(?string $key = null, ?string $value = null): static
	{
		if ($key === null)
			$key = uuidv4();

		if ($value === null)
			unset($this->hosts[$key]);
		else
			$this->hosts[$key] = $value;
		return $this;
	}

	/**
	 * {@see $host_mode_disallow}
	 * @return bool
	 */
	public function isHostModeDisallow(): bool
	{
		return $this->host_mode_disallow;
	}

	/**
	 * {@see $host_mode_disallow}
	 * @param bool $enabled
	 * @return Rule
	 */
	public function setHostModeDisallow(bool $enabled): static
	{
		$this->host_mode_disallow = $enabled;
		return $this;
	}

	/**
	 * {@see $accept_path_info}
	 * @return bool
	 */
	public function isAcceptPathInfo(): bool
	{
		return $this->accept_path_info;
	}

	/**
	 * {@see $accept_path_info}
	 * @param bool $enabled
	 * @return Rule
	 */
	public function setAcceptPathInfo(bool $enabled): static
	{
		$this->accept_path_info = $enabled;
		return $this;
	}

	/**
	 * {@see $process_next}
	 * @return bool
	 */
	public function isProcessNext(): bool
	{
		return $this->process_next;
	}

	/**
	 * {@see $process_next}
	 * @param bool $enabled
	 * @return Rule
	 */
	public function setProcessNext(bool $enabled): static
	{
		$this->process_next = $enabled;
		return $this;
	}

	/**
	 * {@see $reverse}
	 * @return string|null
	 */
	public function getReverse(): ?string
	{
		return $this->reverse;
	}

	/**
	 * {@see $reverse}
	 * @param string|null $path
	 * @return Rule
	 */
	public function setReverse(?string $path): static
	{
		$this->reverse = $path;
		return $this;
	}

	/**
	 * See {@see $priority}
	 * @param int $priority
	 * @return Rule
	 */
	public function setPriority(
		#[ExpectedValues(valuesFromClass: PRIORITY::class)]
		int $priority
	): static
	{
		$this->priority = $priority;
		return $this;
	}

	/**
	 * See {@see $priority}
	 * @return int
	 */
	public function getPriority(): int
	{
		return $this->priority;
	}

	/**
	 * return the name instead of the index
	 * See {@see $priority}
	 * @return string
	 */
	public function getPriorityDesc(): string
	{
		return PRIORITY::_DESCR[$this->priority];
	}

	/**
	 * {@see $sources}
	 * @return bool
	 */
	public function hasSources(): bool
	{
		return count($this->sources) > 0;
	}

	/**
	 * {@see $sources}
	 * @return array
	 */
	public function getSources(): array
	{
		return $this->sources;
	}

	/**
	 * {@see $sources}
	 * @param string $key
	 * @return string|null
	 */
	public function getSource(string $key): ?string
	{
		return $this->sources[$key];
	}

	/**
	 * {@see $sources}
	 * @param string|null $key
	 * @param string|null $path
	 * @return Rule
	 * @throws \Exception
	 */
	public function setSource(?string $key = null, ?string $path = null): static
	{
		if ($key === null)
			$key = uuidv4();

		if ($path === null)
			unset($this->sources[$key]);
		else
			$this->sources[$key] = $path;
		return $this;
	}

	/**
	 * {@see $destinations}
	 * @return bool
	 */
	public function hasDestinations(): bool
	{
		return count($this->destination) > 0;
	}

	/**
	 * {@see $destinations}
	 * @return array
	 */
	public function getDestinations(): array
	{
		return $this->destination;
	}

	/**
	 * {@see $destinations}
	 * @param string $key
	 * @return string|null
	 */
	public function getDestination(string $key): ?string
	{
		return $this->destination[$key];
	}

	/**
	 * {@see $destinations}
	 * @param string|null $key
	 * @param string|null $path
	 * @return Rule
	 * @throws \Exception
	 */
	public function setDestination(?string $key = null, ?string $path = null): static
	{
		if ($key === null)
			$key = uuidv4();

		if ($path === null)
			unset($this->destination[$key]);
		else
			$this->destination[$key] = $path;
		return $this;
	}
}
