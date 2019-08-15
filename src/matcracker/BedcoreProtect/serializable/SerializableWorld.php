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
    /**@var int|null $x */
    protected $x;
    /**@var int|null $y */
    protected $y;
    /**@var int|null $z */
    protected $z;
    /**@var string|null $worldName */
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
    public static function toSerializableWorld(Vector3 $vector3): self
    {
        $worldName = null;
        if ($vector3 instanceof Position) {
            if (($world = $vector3->getLevel()) !== null) {
                $worldName = $world->getName();
            }
        }

        return new self($vector3->getX(), $vector3->getY(), $vector3->getZ(), $worldName);
    }

    /**
     * @return int|null
     */
    public function getX(): ?int
    {
        return $this->x;
    }

    /**
     * @return int|null
     */
    public function getY(): ?int
    {
        return $this->y;
    }

    /**
     * @return int|null
     */
    public function getZ(): ?int
    {
        return $this->z;
    }

    /**
     * @return string|null
     */
    public function getWorldName(): ?string
    {
        return $this->worldName;
    }
}