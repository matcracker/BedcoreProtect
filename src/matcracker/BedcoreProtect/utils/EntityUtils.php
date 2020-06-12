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

namespace matcracker\BedcoreProtect\utils;

use InvalidArgumentException;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use ReflectionClass;
use ReflectionException;
use UnexpectedValueException;
use function array_key_exists;
use function array_merge;
use function strval;

final class EntityUtils
{
    private function __construct()
    {
    }

    /**
     * Returns the entity UUID or the network ID.
     *
     * @param Entity $entity
     *
     * @return string
     * @internal
     */
    public static function getUniqueId(Entity $entity): string
    {
        if ($entity instanceof Human) {
            if (($uuid = $entity->getUniqueId()) === null) {
                throw new UnexpectedValueException($entity->getName() . " has an invalid UUID.");
            }

            return $uuid->toString();
        }

        return strval($entity::NETWORK_ID);
    }

    /**
     * Returns the entity name if is a Living instance else the entity class name.
     *
     * @param Entity $entity
     *
     * @return string
     * @internal
     */
    public static function getName(Entity $entity): string
    {
        try {
            return ($entity instanceof Living) ? $entity->getName() : (new ReflectionClass($entity))->getShortName();
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException('Invalid entity class.');
        }
    }

    /**
     * @param Entity $entity
     * @return string
     */
    public static function getSerializedNbt(Entity $entity): string
    {
        $entity->saveNBT();
        $namedTag = clone $entity->namedtag;
        $namedTag->setShort("Fire", 0);

        if ($entity instanceof Living) {
            $namedTag->setFloat("Health", $entity->getMaxHealth());
        }

        return Utils::serializeNBT($namedTag);
    }

    /**
     * Returns an array with all registered entities save names
     * @return string[]
     */
    public static function getSaveNames(): array
    {
        $names = [];

        $values = (array)self::getEntityProperty('saveNames');
        foreach ($values as $value) {
            $names = array_merge($names, $value);
        }

        return $names;
    }

    /**
     * Returns an array with all registered entities.
     * @return string[]
     */
    public static function getKnownEntities(): array
    {
        return (array)self::getEntityProperty('knownEntities');
    }

    /**
     * Returns the entity class from the its network ID.
     * @param int $networkId
     * @return string
     * @phpstan-return class-string<Entity>
     */
    public static function getClassByNetworkId(int $networkId): string
    {
        $values = self::getKnownEntities();
        if (!array_key_exists($networkId, $values)) {
            throw new InvalidArgumentException("The network ID \"{$networkId}\" is not registered.");
        }

        return $values[$networkId];
    }

    /**
     * @param string $property
     * @return mixed
     */
    private static function getEntityProperty(string $property)
    {
        //HACK ^-^
        try {
            $r = new ReflectionClass(Entity::class);
            $property = $r->getProperty($property);
            $property->setAccessible(true);

            return $property->getValue();
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException("Could not get \"{$property}\" entity property.");
        }
    }
}
