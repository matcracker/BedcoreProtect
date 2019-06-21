<?php

/*
 * BedcoreProtect
 *
 * Copyright (C) 2019
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author matcracker
 * @link https://www.github.com/matcracker/BedcoreProtect
 *
*/

declare(strict_types=1);

namespace matcracker\BedcoreProtect {

	use Phar;

	if(!strpos(__FILE__, ".phar")){
		echo "/-------------------------------<!WARNING!>----------------.---------------\\\n";
		echo "|         It is not recommended to run BedcoreProtect from source.         |\n";
		echo "|You can get a packaged release at https://poggit.pmmp.io/p/BedcoreProtect/|\n";
		echo "\--------------------------------------------------------------------------/\n";
	}
	require_once(getVendorPath() . "/vendor/autoloader.php");

	function getVendorPath() : string{
		return strpos(__FILE__, ".phar") ? Phar::running() : __DIR__;
	}
}

__HALT_COMPILER();