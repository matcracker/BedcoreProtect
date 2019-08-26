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

namespace matcracker\BedcoreProtect\math;

use matcracker\BedcoreProtect\serializable\SerializableWorld;
use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Server;

final class Area
{

    /**@var string $worldName */
    private $worldName;
    /**@var AxisAlignedBB $bb */
    private $bb;

    public function __construct(Level $world, AxisAlignedBB $bb)
    {
        $this->worldName = $world->getName();
        $this->bb = $bb;
    }

    /**
     * @return string
     */
    public function getWorldName(): string
    {
        return $this->worldName;
    }

    public function getBoundingBox(): AxisAlignedBB
    {
        return $this->bb;
    }

    public function getWorld(): ?Level
    {
        return Server::getInstance()->getLevelByName($this->worldName);
    }

    /**
     * @return Chunk[]
     */
    public function getAllChunks(): array
    {
        $areaChunks = [];
        for ($x = $this->bb->minX; $x <= $this->bb->maxX; $x += 16) {
            for ($z = $this->bb->minZ; $z <= $this->bb->maxZ; $z += 16) {
                $chunk = $this->getWorld()->getChunk($x >> 4, $z >> 4, true);
                if ($chunk === null) {
                    continue;
                }
                $areaChunks[Level::chunkHash($x >> 4, $z >> 4)] = $chunk;
            }
        }
        return $areaChunks;
    }

    /**
     * @param SerializableWorld[]|Block[]|Vector3[] $positions
     * @return Chunk[]
     */
    public function getTouchedChunks(array $positions): array
    {
        $touchedChunks = [];
        foreach ($positions as $position) {
            $x = $position->getX() >> 4;
            $z = $position->getZ() >> 4;
            $chunk = $this->getWorld()->getChunk($x, $z);
            if ($chunk === null) {
                continue;
            }
            $touchedChunks[Level::chunkHash($x, $z)] = $chunk;
        }
        return $touchedChunks;
    }

}