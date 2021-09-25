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

class MatchedRule
{
	private Rule $rule;
	private string $source;
	private ?array $params;
	private ?array $host_params;

	public function __construct(
		Rule   $rule,
		string $source,
		?array $params = null,
		?array $host_params = null,
	)
	{
		$this->rule = $rule;
		$this->source = $source;
		$this->params = $params;
		$this->host_params = $host_params;
	}

	/**
	 * @return Rule
	 */
	public function getRule(): Rule
	{
		return $this->rule;
	}

	/**
	 * @return string
	 */
	public function getSource(): string
	{
		return $this->source;
	}

	/**
	 * @return array|null
	 */
	public function getParams(): ?array
	{
		return $this->params;
	}

	/**
	 * @return array|null
	 */
	public function getHostParams(): ?array
	{
		return $this->host_params;
	}
}