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

use InvalidArgumentException;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\WorldUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use function get_class;

final class SerializableBlock extends AbstractSerializable
{
    /** @var string */
    private $name;
    /** @var int */
    private $id;
    /** @var int */
    private $meta;
    /** @var int */
    private $x;
    /** @var int */
    private $y;
    /** @var int */
    private $z;
    /** @var string */
    private $worldName;
    /** @var string|null */
    private $serializedNbt;

    public function __construct(string $name, int $id, int $meta, int $x, int $y, int $z, string $worldName, ?string $serializedNbt = null)
    {
        $this->name = $name;
        $this->id = $id;
        $this->meta = $meta;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->worldName = $worldName;
        $this->serializedNbt = $serializedNbt;
    }

    /**
     * @param Block $object
     * @return SerializableBlock
     */
    public static function serialize($object): AbstractSerializable
    {
        if (!$object instanceof Block) {
            throw new InvalidArgumentException("Expected Block instance, got " . get_class($object));
        }

        return new self(
            $object->getName(),
            $object->getId(),
            $object->getDamage(),
            (int)$object->getX(),
            (int)$object->getY(),
            (int)$object->getZ(),
            $object->getLevelNonNull()->getFolderName(),
            BlockUtils::serializeTileTag($object)
        );
    }

    public function unserialize(): Block
    {
        return BlockFactory::get($this->id, $this->meta, $this->asPosition());
    }

    public function asPosition(): Position
    {
        return new Position($this->x, $this->y, $this->z, WorldUtils::getNonNullWorldByName($this->worldName));
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
        return new Vector3($this->x, $this->y, $this->z);
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getZ(): int
    {
        return $this->z;
    }

    public function getWorldName(): string
    {
        return $this->worldName;
    }

    public function __toString(): string
    {
        return "SerializableBlock(id=$this->id,meta=$this->meta,x=$this->x,y=$this->y,z=$this->z,world=$this->worldName)";
    }
}
