<?php

/*
 * BedcoreProtect
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
use Time\Unit\TimeUnitSecondsInterface;

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

        $strDate = preg_replace('[^0-9smhdw]', '', $strDate);
        if (empty($strDate)) return 0;

        if (strpos($strDate, 'w') !== false) {
            $strDate = preg_replace('[^0-9]', '', $strDate);
            if (empty($strDate)) return 0;

            return TimeUnitDay::toSeconds(((int)$strDate) * 7);
        }
        /**@var TimeUnitSecondsInterface $unit */
        $unit = strpos($strDate, 'd') !== false ? new TimeUnitDay()
            : strpos($strDate, 'h') !== false ? new TimeUnitHour()
                : strpos($strDate, 'm') !== false ? new TimeUnitMinute()
                    : new TimeUnitSecond();

        $strDate = preg_replace('[^0-9]', '', $strDate);
        if (empty($strDate)) return 0;

        return $unit::toSeconds(((int)$strDate));
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