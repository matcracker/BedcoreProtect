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
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use UnexpectedValueException;
use function array_filter;
use function base64_decode;
use function base64_encode;
use function count;
use function is_string;
use function json_decode;
use function mb_strpos;
use function microtime;
use function preg_match;
use function preg_replace;
use function strlen;
use function zlib_decode;

final class Utils
{
    private function __construct()
    {
        //NOOP
    }

    /**
     * It parses a string type like "XwXdXhXmXs" where X is a number indicating the time.
     *
     * @param string $strDate the date to parse.
     *
     * @return int|null
     */
    public static function parseTime(string $strDate): ?int
    {
        /** @var string[] $matches */
        preg_match("/([0-9]+)(?i)([smhdw])(?-i)/", $strDate, $matches);

        if (count($matches) === 0) {
            return null;
        }

        $value = preg_replace("/[^0-9]/", "", $matches[1]);
        if (!is_string($value)) {
            throw new UnexpectedValueException("Invalid time parsed, expected string.");
        }

        $dateType = preg_replace("/[^smhdw]/", "", $matches[2]);
        if (!is_string($dateType)) {
            throw new UnexpectedValueException("Invalid date type parsed, expected string.");
        }

        $time = 0;
        $value = (int)$value;

        $time += match ($dateType) {
            "w" => $value * 7 * 24 * 60 * 60,
            "d" => $value * 24 * 60 * 60,
            "h" => $value * 60 * 60,
            "m" => $value * 60,
            "s" => $value,
        };

        return (int)$time;
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
     */
    public static function serializeNBT(CompoundTag $tag): string
    {
        //Encoding to Base64 for more safe storing.
        return base64_encode((new BigEndianNbtSerializer())->write(new TreeRoot($tag)));
    }

    /**
     * It deserializes a Base64 string to a CompoundTag.
     */
    public static function deserializeNBT(string $encodedData): CompoundTag
    {
        $data = base64_decode($encodedData);
        /*
         * This is necessary to maintain the compatibility with previous
         * plugin versions which use a compressed NBT.
         */
        if (self::isCompressedString($data)) {
            $data = zlib_decode($data);
        }

        return (new BigEndianNbtSerializer())->read($data)->mustGetCompoundTag();
    }

    private static function isCompressedString(string $str): bool
    {
        return mb_strpos($str, "\x1f\x8b\x08") === 0;
    }
}
