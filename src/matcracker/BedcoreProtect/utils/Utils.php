<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2021
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
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\NamedTag;
use pocketmine\Player;
use pocketmine\Server;
use UnexpectedValueException;
use function array_filter;
use function array_map;
use function base64_decode;
use function base64_encode;
use function count;
use function is_string;
use function json_decode;
use function microtime;
use function min;
use function preg_match;
use function preg_replace;
use function strlen;
use const PHP_INT_MAX;
use const PREG_OFFSET_CAPTURE;

final class Utils
{
    private function __construct()
    {
    }

    /**
     * It parses a string type like "XwXdXhXmXs" where X is a number indicating the time.
     *
     * @param string $strDate the date to parse.
     *
     * @return int
     */
    public static function parseTime(string $strDate): int
    {
        preg_match("/([0-9]+)(?i)([smhdw])(?-i)/", $strDate, $matches, PREG_OFFSET_CAPTURE);

        if (count($matches) === 0) {
            return 0;
        }

        $time = 0;
        foreach ($matches[0] as $match) {
            $value = preg_replace("/[^0-9]/", "", $match);
            if (!is_string($value)) {
                throw new UnexpectedValueException("Invalid time parsed, expected string.");
            }
            $value = (int)$value;

            $dateType = preg_replace("/[^smhdw]/", "", $match);
            if (!is_string($dateType)) {
                throw new UnexpectedValueException("Invalid date type parsed, expected string.");
            }

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

        return (int)min($time, PHP_INT_MAX);
    }

    public static function getSenderUUID(CommandSender $sender): string
    {
        return $sender instanceof Player ? EntityUtils::getUniqueId($sender) : $sender->getServer()->getServerUniqueId()->toString();
    }

    public static function timeAgo(int $timestamp): string
    {
        $currentDate = DateTime::createFromFormat("0.u00 U", microtime());
        if ($currentDate === false) {
            throw new UnexpectedValueException("Unexpected date creation.");
        }

        $json = (new DateTime())
            ->setTimestamp($timestamp)
            ->diff($currentDate)
            ->format("{\"y\":%y,\"mon\":%m,\"d\":%d,\"h\":%h,\"m\":%i,\"s\":%s}");

        //Format date and time object by creating a JSON string
        $jsonArr = array_filter((array)json_decode($json, true));

        $string = "";
        foreach ($jsonArr as $key => $val) {
            $string .= "$val$key";
        }

        if (strlen($string) > 0) {
            return "$string ago";
        } else {
            return "Just now";
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

        $compressedData = $nbtSerializer->writeCompressed($tag);

        if (!is_string($compressedData)) {
            throw new UnexpectedValueException("Invalid compression of NBT data.");
        }

        //Encoding to Base64 for more safe storing.
        return base64_encode($compressedData);
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

        /** @var NamedTag $tag */
        $tag = $nbtSerializer->readCompressed(base64_decode($encodedData));

        if (!($tag instanceof CompoundTag)) {
            throw new UnexpectedValueException("Value must return CompoundTag, got " . $tag->__toString());
        }

        return $tag;
    }

    public static function getWorldNames(): array
    {
        return array_map(static function (Level $world): string {
            return $world->getFolderName();
        }, Server::getInstance()->getLevels());
    }
}
