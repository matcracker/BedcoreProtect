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

declare(strict_types=1);

namespace matcracker\BedcoreProtect\utils;

use InvalidStateException;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Server;

final class WorldUtils
{
    private function __construct()
    {
        //NOOP
    }

    /**
     * @param Level $world
     * @param SerializableBlock[]|Vector3[] $positions
     * @return Chunk[]
     */
    public static function getChunks(Level $world, array $positions): array
    {
        $touchedChunks = [];
        foreach ($positions as $position) {
            $x = $position->getX() >> 4;
            $z = $position->getZ() >> 4;
            $chunk = $world->getChunk($x, $z);
            if ($chunk === null) {
                continue;
            }
            $touchedChunks[Level::chunkHash($x, $z)] = $chunk;
        }

        return $touchedChunks;
    }

    public static function getNonNullWorldByName(string $worldName): Level
    {
        $world = Server::getInstance()->getLevelByName($worldName);
        if ($world === null) {
            throw new InvalidStateException("World \"$worldName\" does not exist.");
        }

        return $world;
    }
}