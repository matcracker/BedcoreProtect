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

use InvalidArgumentException;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use function get_class;
use const PHP_EOL;

final class SerializableBlock extends SerializablePosition
{
    /** @var string */
    private $name;
    /** @var int */
    private $id;
    /** @var int */
    private $meta;
    /** @var string|null */
    private $serializedNbt;

    public function __construct(string $name, int $id, int $meta, ?int $x, ?int $y, ?int $z, ?string $worldName, ?string $serializedNbt = null)
    {
        parent::__construct((float)$x, (float)$y, (float)$z, $worldName);
        $this->name = $name;
        $this->id = $id;
        $this->meta = $meta;
        $this->serializedNbt = $serializedNbt;
    }

    /**
     * @param Block $block
     * @return SerializableBlock
     */
    public static function serialize($block): AbstractSerializable
    {
        if (!$block instanceof Block) {
            throw new InvalidArgumentException("Expected Block instance, got " . get_class($block));
        }

        return new self(
            $block->getName(),
            $block->getId(),
            $block->getDamage(),
            (int)$block->getX(),
            (int)$block->getY(),
            (int)$block->getZ(),
            parent::serialize($block)->worldName,
            BlockUtils::serializeTileTag($block)
        );
    }

    /**
     * @return Block
     */
    public function unserialize()
    {
        return BlockFactory::get($this->id, $this->meta, parent::unserialize());
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

    public function __toString(): string
    {
        return parent::__toString() . PHP_EOL . "SerializableBlock(id={$this->id},meta={$this->meta})";
    }
}
