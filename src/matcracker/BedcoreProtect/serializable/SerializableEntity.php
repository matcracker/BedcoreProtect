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
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\Player;
use RuntimeException;
use function get_class;

final class SerializableEntity extends SerializablePosition
{
    /** @var string */
    private $uuid;
    /** @var int */
    private $id;
    /** @var string */
    private $name;
    /** @var string */
    private $classPath;
    /** @var string */
    private $address;
    /** @var string */
    private $serializedNbt;

    public function __construct(string $uuid, int $id, string $name, string $classPath, string $address, ?float $x, ?float $y, ?float $z, ?string $worldName, string $serializedNbt)
    {
        parent::__construct($x ?? 0, $y ?? 0, $z ?? 0, $worldName);
        $this->uuid = $uuid;
        $this->id = $id;
        $this->name = $name;
        $this->classPath = $classPath;
        $this->address = $address;
        $this->serializedNbt = $serializedNbt;
    }

    /**
     * @param Entity $entity
     * @return SerializableEntity
     */
    public static function serialize($entity): AbstractSerializable
    {
        $classPath = get_class($entity);
        if (!$entity instanceof Entity) {
            throw new InvalidArgumentException("Expected Entity instance, got " . $classPath);
        }

        if (!$entity instanceof Player) {
            $entity->saveNBT();

            if ($entity instanceof Living) {
                $entity->namedtag->setFloat("Health", $entity->getMaxHealth());
            }
        }

        return new self(
            EntityUtils::getUniqueId($entity),
            $entity->getId(),
            EntityUtils::getName($entity),
            $classPath,
            ($entity instanceof Player) ? $entity->getAddress() : "127.0.0.1",
            (float)$entity->getX(),
            (float)$entity->getY(),
            (float)$entity->getZ(),
            parent::serialize($entity->asPosition())->worldName,
            Utils::serializeNBT($entity->namedtag)
        );
    }

    /**
     * @return Entity|null
     */
    public function unserialize()
    {
        if (($level = parent::unserialize()->getLevel()) === null) {
            throw new RuntimeException("Could not create an entity with \"null\" world.");
        }

        /** @var Entity $classPath */
        $classPath = $this->classPath;
        return Entity::createEntity($classPath::NETWORK_ID, $level, Utils::deserializeNBT($this->serializedNbt));
    }

    public function getUniqueId(): string
    {
        return $this->uuid;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getClassPath(): string
    {
        return $this->classPath;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSerializedNbt(): string
    {
        return $this->serializedNbt;
    }

    public function __toString(): string
    {
        return "SerializableEntity({$this->uuid}:{$this->name})({$this->classPath})[{$this->worldName}]";
    }
}
