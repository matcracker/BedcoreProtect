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
use matcracker\BedcoreProtect\storage\QueriesConst;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use ReflectionClass;
use ReflectionException;
use Time\Unit\TimeUnitDay;
use Time\Unit\TimeUnitHour;
use Time\Unit\TimeUnitMinute;
use Time\Unit\TimeUnitSecond;

final class Utils
{

    private function __construct()
    {
    }

    /*
     * It returns the action's name from
     * @param int $action
     * @return string
     */
    public static function getActionName(int $action): string
    {
        $class = new ReflectionClass(QueriesConst::class);
        $map = array_flip(array_filter($class->getConstants(), function ($element) {
            return is_int($element);
        }));

        if (array_key_exists($action, $map)) {
            return strtolower($map[$action]);
        } else {
            throw new InvalidArgumentException('This action does not exist.');
        }
    }

    public static function translateColors(string $message): string
    {
        return preg_replace_callback("/(\\\&|\&)[0-9a-fk-or]/", function (array $matches): string {
            return str_replace(TextFormat::RESET, TextFormat::RESET . TextFormat::WHITE, str_replace("\\" . TextFormat::ESCAPE, '&', str_replace('&', TextFormat::ESCAPE, $matches[0])));
        }, $message);
    }

    /**
     * It parses a string type like 'XwXdXhXmXs' where X is a number indicating the time.
     *
     * @param string $strDate the date to parse.
     * @return int how many seconds are in the string.
     */
    public static function parseTime(string $strDate): int
    {
        if (empty($strDate)) return 0;
        $strDate = preg_replace("/[^0-9smhdw]/", "", $strDate);
        if (empty($strDate)) return 0;

        $time = 0;
        $matches = [];
        preg_match_all("/([0-9]{1,})([smhdw]{1})/", $strDate, $matches);

        foreach ($matches[0] as $match) {
            $value = (int)preg_replace("/[^0-9]/", "", $match);
            $dateType = (string)preg_replace("/[^smhdw]/", "", $match);

            switch ($dateType) {
                case "w":
                    $time += TimeUnitDay::toSeconds($value * 7);
                    break;
                case "d":
                    $time += TimeUnitDay::toSeconds($value);
                    break;
                case "h":
                    $time += TimeUnitHour::toSeconds($value);
                    break;
                case "m":
                    $time += TimeUnitMinute::toSeconds($value);
                    break;
                case "s":
                    $time += TimeUnitSecond::toSeconds($value);
                    break;
            }
        }
        return $time;
    }

    /**
     * Returns the entity UUID.
     * @param Entity $entity
     * @return string
     * @internal
     */
    public static function getEntityUniqueId(Entity $entity): string
    {
        return ($entity instanceof Human) ? $entity->getUniqueId()->toString() : strval($entity::NETWORK_ID);
    }

    /**
     * @param Entity $entity
     * @return string
     * @internal
     */
    public static function getEntityName(Entity $entity): ?string
    {
        try {
            $reflect = new ReflectionClass($entity);
            return ($entity instanceof Player) ? $entity->getName() : $reflect->getShortName();
        } catch (ReflectionException $e) {
            return null;
        }
    }
}