<?php
/**
 * @package FormsFramework
 * @subpackage Common
 * @author Samuele Diella <samuele.diella@gmail.com>
 * @copyright Copyright (c) 2004-2021, Samuele Diella
 * @license https://opensource.org/licenses/LGPL-3.0
 * @link https://www.ffphp.com
 */

namespace FF\Core\Common;

class Autoloader
{
    private string $dir;
    private array $mapping = [];

    private function __construct($dir = null, array $alternate_maps = [])
    {
        $this->dir = rtrim(
        	$dir ?? str_contains(__DIR__, "/vendor/") ? dirname(__DIR__) : dirname(dirname(__DIR__))
			, DIRECTORY_SEPARATOR);
        $this->mapping = $alternate_maps;
    }
	
    /**
     * Registers as an SPL autoloader.
     */
    public static function register($dir = null, array $alternate_maps = []): bool
	{
        ini_set("unserialize_callback_func", "spl_autoload_call");
        return spl_autoload_register(array(new self($dir, $alternate_maps), "autoload"));
    }

    public function autoload(string $class): void
	{
        if (!str_starts_with($class, "FF\\"))
            return;
		
		$parts = explode("\\", $class);
		unset($parts[0]);
		$class_name = array_pop($parts);

		static $processed = [];
		$partial = "";
		$dir = "";
		foreach ($parts as $part)
		{
			$partial .= (strlen($partial) ? "\\" : "") . $part;
			$dir .= (strlen($dir) ? DIRECTORY_SEPARATOR : "") . $part;

			$dir = $this->mapping[$partial] ?? $dir;

			// common files
			if (isset($processed[$dir]))
				continue;

			if (file_exists($this->dir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . "constants.php"))
				@include_once($this->dir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . "constants.php");
			if (file_exists($this->dir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . "helpers.php"))
				@include_once($this->dir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . "helpers.php");
			$processed[$dir] = true;
		}
		
        if (file_exists($file = $this->dir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $class_name . ".php"))
		{
            require $file;
		}
		else
		{
			echo "AUTOLOAD NOT FOUND: " . $class . " - " . $file . "\n";
			var_dump(debug_backtrace());
			exit;
		}
    }
}
