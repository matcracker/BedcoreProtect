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

    /**
     * @param bool $asVector
     * @return \Generator
     */
    public function getBlocksInside(bool $asVector): \Generator
    {
        for ($x = $this->bb->minX; $x <= $this->bb->maxX; $x++) {
            for ($y = $this->bb->minY; $y <= $this->bb->maxY; $y++) {
                for ($z = $this->bb->minZ; $z <= $this->bb->maxZ; $z++) {
                    yield $asVector ? new Vector3((int)$x, (int)$y, (int)$z) : $this->getWorld()->getBlock(new Vector3($x, $y, $z));
                }
            }
        }
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
        $touchedChunks = [];
        for ($x = $this->bb->minX; $x <= $this->bb->maxX; $x += 16) {
            for ($z = $this->bb->minZ; $z <= $this->bb->maxZ; $z += 16) {
                $chunk = $this->getWorld()->getChunk($x >> 4, $z >> 4, true);
                if ($chunk === null) {
                    continue;
                }
                $touchedChunks[Level::chunkHash($x >> 4, $z >> 4)] = $chunk;
            }
        }
        return $touchedChunks;
    }

    /**
     * @param PrimitiveBlock[] $blocks
     * @return Chunk[]
     */
    public function getBlockChunks(array $blocks): array
    {
        $touchedChunks = [];
        foreach ($blocks as $block) {
            $x = $block->getX() >> 4;
            $z = $block->getZ() >> 4;
            $chunk = $this->getWorld()->getChunk($x, $z);
            if ($chunk === null) {
                continue;
            }
            $touchedChunks[Level::chunkHash($x, $z)] = $chunk;
        }
        return $touchedChunks;
    }

}