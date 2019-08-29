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

use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;

final class SerializableItem
{
    /**@var int $id */
    private $id;
    /**@var int $meta */
    private $meta;
    /**@var int $count */
    private $count;
    /**@var string $serializedNbt */
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
    public static function toSerializableItem(Item $item): self
    {
        return new self($item->getId(), $item->getDamage(), $item->getCount(), Utils::serializeNBT($item->getNamedTag()));
    }

    public function toItem(): Item
    {
        return ItemFactory::get($this->id, $this->meta, $this->count, Utils::deserializeNBT($this->serializedNbt));
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
    public function getCount(): int
    {
        return $this->count;
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
        return "SerializableItem({$this->id}:{$this->meta})[{$this->count}]";
    }
}