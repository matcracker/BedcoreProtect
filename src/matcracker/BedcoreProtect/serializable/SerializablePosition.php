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

use InvalidArgumentException;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Server;
use function get_class;

class SerializablePosition extends AbstractSerializable
{
    /** @var float */
    protected $x;
    /** @var float */
    protected $y;
    /** @var float */
    protected $z;
    /** @var string|null */
    protected $worldName;

    public function __construct(float $x, float $y, float $z, ?string $worldName)
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->worldName = $worldName;
    }

    /**
     * @param Vector3 $vector3
     * @return SerializablePosition
     */
    public static function serialize($vector3): AbstractSerializable
    {
        if (!$vector3 instanceof Vector3) {
            throw new InvalidArgumentException("Expected Vector3 instance, got " . get_class($vector3));
        }

        $worldName = null;
        if ($vector3 instanceof Position) {
            if (($world = $vector3->getLevel()) !== null) {
                $worldName = $world->getName();
            }
        }

        return new self((float)$vector3->getX(), (float)$vector3->getY(), (float)$vector3->getZ(), $worldName);
    }

    final public function getX(): float
    {
        return $this->x;
    }

    final public function getY(): float
    {
        return $this->y;
    }

    final public function getZ(): float
    {
        return $this->z;
    }

    final public function getWorldName(): ?string
    {
        return $this->worldName;
    }

    /**
     * @return Position
     */
    public function unserialize()
    {
        $world = $this->worldName !== null ? Server::getInstance()->getLevelByName($this->worldName) : null;
        return new Position($this->x, $this->y, $this->z, $world);
    }

    public function __toString(): string
    {
        return "SerializablePosition(x={$this->x},y={$this->y},z={$this->z},world={$this->worldName})";
    }
}
