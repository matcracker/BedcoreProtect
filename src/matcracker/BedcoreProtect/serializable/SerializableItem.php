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
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use function get_class;

final class SerializableItem extends AbstractSerializable
{
    /** @var int */
    private $id;
    /** @var int */
    private $meta;
    /** @var int */
    private $count;
    /** @var string */
    private $serializedNbt;

    public function __construct(int $id, int $meta, int $count, string $serializedNbt)
    {
        $this->id = $id;
        $this->meta = $meta;
        $this->count = $count;
        $this->serializedNbt = $serializedNbt;
    }

    /**
     * @param Item $item
     * @return SerializableItem
     */
    public static function serialize($item): AbstractSerializable
    {
        if (!$item instanceof Item) {
            throw new InvalidArgumentException("Expected Item instance, got " . get_class($item));
        }

        return new self($item->getId(), $item->getDamage(), $item->getCount(), Utils::serializeNBT($item->getNamedTag()));
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMeta(): int
    {
        return $this->meta;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getSerializedNbt(): string
    {
        return $this->serializedNbt;
    }

    public function __toString(): string
    {
        return "SerializableItem({$this->id}:{$this->meta})[{$this->count}]";
    }

    /**
     * @return Item
     */
    public function unserialize()
    {
        return ItemFactory::get($this->id, $this->meta, $this->count, Utils::deserializeNBT($this->serializedNbt));
    }
}
