<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2021
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

namespace matcracker\BedcoreProtect\utils;

use function array_values;
use function count;
use function current;
use function next;

final class ArrayUtils
{
    private function __construct()
    {
        //NOOP
    }

    /**
     * Check if all the given arrays are the same dimension.
     */
    public static function checkSameDimension(array ...$list): bool
    {
        while ($current = current($list)) {
            $next = next($list);
            if ($next !== false && count($next) !== count($current)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reset all the keys of each array to numeric values.
     */
    public static function resetKeys(array &...$list): void
    {
        foreach ($list as &$arr) {
            $arr = array_values($arr);
        }
    }
}