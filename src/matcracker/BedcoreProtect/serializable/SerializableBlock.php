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

namespace matcracker\BedcoreProtect\serializable;

use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\level\Position;
use pocketmine\Server;

final class SerializableBlock extends SerializableWorld
{
    /**@var int $id */
    private $id;
    /**@var int $meta */
    private $meta;
    /**@var string|null $serializedNbt */
    private $serializedNbt;

    public function __construct(int $id, int $meta, ?int $x, ?int $y, ?int $z, ?string $worldName, ?string $serializedNbt = null)
    {
        parent::__construct($x, $y, $z, $worldName);
        $this->id = $id;
        $this->meta = $meta;
        $this->serializedNbt = $serializedNbt;
    }

    /**
     * @param Block $block
     * @return SerializableBlock
     */
    public static function toSerializableBlock(Block $block): self
    {
        $worldName = null;
        if (($world = $block->getLevel()) !== null) {
            $worldName = $world->getName();
        }
        return new self($block->getId(), $block->getDamage(), $block->getX(), $block->getY(), $block->getZ(), $worldName, BlockUtils::serializeBlockTileNBT($block));
    }

    public function toBlock(): Block
    {
        $world = Server::getInstance()->getLevelByName($this->worldName);
        return BlockFactory::get($this->id, $this->meta, new Position($this->x, $this->y, $this->z, $world));
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
     * @return string|null
     */
    public function getSerializedNbt(): ?string
    {
        return $this->serializedNbt;
    }

    public function __toString(): string
    {
        return "SerializableBlock({$this->id}:{$this->meta})[{$this->worldName}]";
    }
}