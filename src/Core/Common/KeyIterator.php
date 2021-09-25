<?php
/**
 * This file is part of FF: Forms PHP Framework
 *
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://ffphp.com
 */

namespace FF\Core\Common;

class KeyIterator implements \Iterator
{
	protected int $position;
	protected array $keys;
	protected array $values;

	public function __construct(array $array)
	{
		$this->position = 0;
		$this->keys = array_keys($array);
		$this->values = array_values($array);
	}

	public function current()
	{
		return $this->values[$this->position];
	}

	public function next(): void
	{
		++$this->position;
	}

	public function key(): string
	{
		return $this->keys[$this->position];
	}

	public function valid(): bool
	{
		return isset($this->values[$this->position]);
	}

	public function rewind(): void
	{
		$this->position = 0;
	}
}
