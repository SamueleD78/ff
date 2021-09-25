<?php
/**
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link http://www.formsphpframework.com
 */

namespace FF\Core\Common;

trait Orderable {
	static int $_counter = -1;
	public int $counter;

	/**
	 * a numeric index used to further order the elements. The bigger is the
	 * number, the highest priority it will have
	 * @var int
	 */
	public int          		$index 				= 0;

	protected function setCounter(): void
	{
		self::$_counter++;
		$this->counter = self::$_counter;
	}

	/**
	 * See {@see $index}
	 * @param int $index
	 * @return Orderable
	 */
	public function setIndex(int $index): static
	{
		$this->index = $index;
		return $this;
	}

	/**
	 * See {@see $index}
	 * @return int
	 */
	public function getIndex(): int
	{
		return $this->index;
	}
}