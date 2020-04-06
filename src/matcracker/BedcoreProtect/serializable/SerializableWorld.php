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


namespace matcracker\BedcoreProtect\serializable;

use pocketmine\level\Position;
use pocketmine\math\Vector3;

class SerializableWorld
{
    /** @var int|null */
    protected $x;
    /** @var int|null */
    protected $y;
    /** @var int|null */
    protected $z;
    /** @var string|null */
    protected $worldName;

    public function __construct(?int $x, ?int $y, ?int $z, ?string $worldName)
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->worldName = $worldName;
    }

    /**
     * @param Vector3 $vector3
     * @return SerializableWorld
     */
    final public static function toSerializableWorld(Vector3 $vector3): self
    {
        $worldName = null;
        if ($vector3 instanceof Position) {
            if (($world = $vector3->getLevel()) !== null) {
                $worldName = $world->getFolderName();
            }
        }

        return new self((int)$vector3->getX(), (int)$vector3->getY(), (int)$vector3->getZ(), $worldName);
    }

    /**
     * @return int|null
     */
    final public function getX(): ?int
    {
        return $this->x;
    }

    /**
     * @return int|null
     */
    final public function getY(): ?int
    {
        return $this->y;
    }

    /**
     * @return int|null
     */
    final public function getZ(): ?int
    {
        return $this->z;
    }

    /**
     * @return string|null
     */
    final public function getWorldName(): ?string
    {
        return $this->worldName;
    }
}
