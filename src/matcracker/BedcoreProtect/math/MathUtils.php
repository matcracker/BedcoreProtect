<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
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

namespace matcracker\BedcoreProtect\math;

use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

final class MathUtils
{
    private function __construct()
    {
    }

    public static function getRangedVector(Vector3 $vector3, int $range): AxisAlignedBB
    {
        $bb = new AxisAlignedBB($vector3->getX(), $vector3->getY(), $vector3->getZ(), $vector3->getX(), $vector3->getY(), $vector3->getZ());
        return $bb->expand($range, $range, $range);
    }

    public static function floorBoundingBox(AxisAlignedBB $bb): AxisAlignedBB
    {
        return new AxisAlignedBB(
            (int)floor($bb->minX),
            (int)floor($bb->minY),
            (int)floor($bb->minZ),
            (int)floor($bb->maxX),
            (int)floor($bb->maxY),
            (int)floor($bb->maxZ)
        );
    }
}