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

namespace matcracker\BedcoreProtect\serializable;

use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\WorldUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\math\Vector3;
use pocketmine\World\Position;

final class SerializableBlock
{
    public function __construct(
        private string  $name,
        private int     $id,
        private int     $meta,
        private Vector3 $position,
        private string  $worldName,
        private ?string $serializedNbt = null)
    {
    }

    public static function fromBlock(Block $block): self
    {
        return new self(
            $block->getName(),
            $block->getId(),
            $block->getMeta(),
            $block->getPos(),
            $block->getPos()->getWorld()->getFolderName(),
            BlockUtils::serializeTileTag($block)
        );
    }

    public function toBlock(): Block
    {
        return BlockFactory::getInstance()->get($this->id, $this->meta, $this->asPosition());
    }

    public function asPosition(): Position
    {
        return Position::fromObject($this->position, WorldUtils::getNonNullWorldByName($this->worldName));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMeta(): int
    {
        return $this->meta;
    }

    public function getSerializedNbt(): ?string
    {
        return $this->serializedNbt;
    }

    public function asVector3(): Vector3
    {
        return $this->position;
    }

    public function getX(): int
    {
        return $this->position->x;
    }

    public function getY(): int
    {
        return $this->position->y;
    }

    public function getZ(): int
    {
        return $this->position->z;
    }

    public function getWorldName(): string
    {
        return $this->worldName;
    }

    public function __toString(): string
    {
        return "SerializableBlock(id=$this->id,meta=$this->meta,vector=$this->position,world=$this->worldName)";
    }
}
