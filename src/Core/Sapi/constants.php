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

abstract class PRIORITY {
	const TOP 			= 0;
	const VERY_HIGH		= 1;
	const HIGH			= 2;
	const NORMAL 		= 3;
	const LOW			= 4;
	const VERY_LOW		= 5;
	const BOTTOM 		= 6;
	const DEFAULT 		= self::NORMAL;

	const _DESCR = [
		self::TOP 		=> "TOP",
		self::VERY_HIGH => "VERY_HIGH",
		self::HIGH 		=> "HIGH",
		self::NORMAL	=> "NORMAL",
		self::LOW 		=> "LOW",
		self::VERY_LOW 	=> "VERY_LOW",
		self::BOTTOM 	=> "BOTTOM",
		self::DEFAULT 	=> "NORMAL",
	];

	const _REVERSE_DESCR = [
		self::_DESCR[self::TOP] 		=> self::TOP,
		self::_DESCR[self::VERY_HIGH] 	=> self::VERY_HIGH,
		self::_DESCR[self::HIGH]		=> self::HIGH,
		self::_DESCR[self::NORMAL] 		=> self::NORMAL,
		self::_DESCR[self::LOW] 		=> self::LOW,
		self::_DESCR[self::VERY_LOW] 	=> self::VERY_LOW,
		self::_DESCR[self::BOTTOM] 		=> self::BOTTOM,
		self::_DESCR[self::DEFAULT] 	=> self::DEFAULT,
	];
}
