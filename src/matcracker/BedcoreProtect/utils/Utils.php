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

use DateTime;
use InvalidArgumentException;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\level\format\Chunk;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;
use ReflectionClass;
use ReflectionException;
use UnexpectedValueException;

final class Utils
{

    private function __construct()
    {
    }

    /**
     * It translates chat colors from format "§" to "&"
     *
     * @param string $message
     *
     * @return string
     */
    public static function translateColors(string $message): string
    {
        return preg_replace_callback('/(\\\&|\&)[0-9a-fk-or]/', static function (array $matches): string {
            return str_replace(TextFormat::RESET, TextFormat::RESET . TextFormat::WHITE, str_replace('\\' . TextFormat::ESCAPE, '&', str_replace('&', TextFormat::ESCAPE, $matches[0])));
        }, $message);
    }

    /**
     * It parses a string type like 'XwXdXhXmXs' where X is a number indicating the time.
     *
     * @param string $strDate the date to parse.
     *
     * @return int|null how many seconds are in the string. Return null if string can't be parsed.
     */
    public static function parseTime(string $strDate): ?int
    {
        if (empty($strDate)) return null;
        $strDate = strtolower($strDate);
        $strDate = preg_replace('/[^0-9smhdw]/', "", $strDate);
        if (empty($strDate)) return null;

        $time = null;
        $matches = [];
        preg_match_all('/([0-9]+)([smhdw])/', $strDate, $matches);

        foreach ($matches[0] as $match) {
            $value = (int)preg_replace("/[^0-9]/", "", $match);
            $dateType = (string)preg_replace("/[^smhdw]/", "", $match);

            switch ($dateType) {
                case "w":
                    $time += $value * 7 * 24 * 60 * 60;
                    break;
                case "d":
                    $time += $value * 24 * 60 * 60;
                    break;
                case "h":
                    $time += $value * 60 * 60;
                    break;
                case "m":
                    $time += $value * 60;
                    break;
                case "s":
                    $time += $value;
                    break;
            }
        }

        return $time;
    }

    public static function timeAgo(int $timestamp, int $level = 6): string
    {
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        $date = $date->diff(DateTime::createFromFormat('0.u00 U', microtime()));
        // build array
        $since = json_decode($date->format('{"year":%y,"month":%m,"day":%d,"hour":%h,"minute":%i,"second":%s}'), true);
        // remove empty date values
        $since = array_filter($since);
        // output only the first x date values
        $since = array_slice($since, 0, $level);
        // build string
        $last_key = key(array_slice($since, -1, 1, true));
        $string = '';
        foreach ($since as $key => $val) {
            // separator
            if ($string) {
                $string .= $key !== $last_key ? ', ' : ' and ';
            }
            // set plural
            $key .= $val > 1 ? 's' : '';
            // add date value
            $string .= $val . ' ' . $key;
        }

        return $string . ' ago';
    }

    /**
     * Returns the entity UUID or the network ID.
     *
     * @param Entity $entity
     *
     * @return string
     * @internal
     */
    public static function getEntityUniqueId(Entity $entity): string
    {
        return ($entity instanceof Human) ? $entity->getUniqueId()->toString() : strval($entity::NETWORK_ID);
    }

    /**
     * Returns the entity name if is a Living instance else the entity class name.
     *
     * @param Entity $entity
     *
     * @return string
     * @internal
     */
    public static function getEntityName(Entity $entity): string
    {
        try {
            return ($entity instanceof Living) ? $entity->getName() : (new ReflectionClass($entity))->getShortName();
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException('Invalid entity class.');
        }
    }

    /**
     * It serializes the CompoundTag to a Base64 string.
     *
     * @param CompoundTag $tag
     *
     * @return string
     */
    public static function serializeNBT(CompoundTag $tag): string
    {
        $nbtSerializer = new BigEndianNBTStream();

        //Encoding to Base64 for more safe storing.
        return base64_encode($nbtSerializer->writeCompressed($tag));
    }

    /**
     * It de-serializes the CompoundTag to a Base64 string.
     *
     * @param string $encodedData
     *
     * @return CompoundTag
     */
    public static function deserializeNBT(string $encodedData): CompoundTag
    {
        $nbtSerializer = new BigEndianNBTStream();

        $tag = $nbtSerializer->readCompressed(base64_decode($encodedData));

        if (!($tag instanceof CompoundTag)) {
            throw new UnexpectedValueException('Value must return CompoundTag, got ' . get_class($tag));
        }
        return $tag;
    }

    /**
     * Returns an array with all registered entities save names
     * @return array
     */
    public static function getEntitySaveNames(): array
    { //HACK ^-^
        try {
            $r = new ReflectionClass(Entity::class);
            $property = $r->getProperty('saveNames');
            $property->setAccessible(true);
            $names = [];

            $values = array_values((array)$property->getValue());
            foreach ($values as $value) {
                $names = array_merge($names, $value);
            }

            return $names;
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException('Could not get entities names.');
        }
    }

    /**
     * @param Chunk[] $chunks
     * @return string[]
     */
    public static function serializeChunks(array $chunks): array
    {
        return array_map(static function (Chunk $chunk): string {
            return $chunk->fastSerialize();
        }, $chunks);
    }
}