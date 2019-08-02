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

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\level\Position;
use pocketmine\Server;

final class PrimitiveBlock
{
    /**@var string $name */
    private $name;
    /**@var int $id */
    private $id;
    /**@var int $meta */
    private $meta;
    /**@var int $x */
    private $x;
    /**@var int $y */
    private $y;
    /**@var int $z */
    private $z;

    public function __construct(string $name, int $id, int $meta, int $x, int $y, int $z)
    {
        $this->name = $name;
        $this->id = $id;
        $this->meta = $meta;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    /**
     * @param Block $block
     * @return PrimitiveBlock
     */
    public static function toPrimitive(Block $block): self
    {
        return new self($block->getName(), $block->getId(), $block->getDamage(), $block->getX(), $block->getY(), $block->getZ());
    }

    public function toBlock(): Block
    {
        return BlockFactory::get($this->id, $this->meta, new Position($this->x, $this->y, $this->z));
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getMeta(): int
    {
        return $this->meta;
    }

    /**
     * @return int
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * @return int
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * @return int
     */
    public function getZ(): int
    {
        return $this->z;
    }

    public function __toString(): string
    {
        return "PrimitiveBlock[{$this->name}]({$this->id}:{$this->meta})";
    }
}