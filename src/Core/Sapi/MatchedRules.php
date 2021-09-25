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

use FF\Core\Common\KeyIterator;

class MatchedRules extends KeyIterator
{
	/**
	 * @return MatchedRule
	 */
	public function current(): MatchedRule
	{
		return parent::current();
	}
}