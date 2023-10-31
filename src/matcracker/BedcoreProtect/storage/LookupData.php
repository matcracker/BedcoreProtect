<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2023
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

namespace matcracker\BedcoreProtect\storage;

use matcracker\BedcoreProtect\commands\CommandData;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;

class LookupData
{
    /** @var self[] */
    private static array $lookupData = [];

    public function __construct(
        private readonly string        $queryName,
        private readonly int           $rowsCount,
        private readonly CommandSender $sender,
        private readonly CommandData   $commandData,
        private readonly Vector3       $position
    )
    {
    }

    public static function storeData(CommandSender $sender, LookupData $data): void
    {
        self::$lookupData[Utils::getSenderUUID($sender)] = $data;
    }

    public static function getData(CommandSender $sender): ?LookupData
    {
        return self::$lookupData[Utils::getSenderUUID($sender)] ?? null;
    }

    public function getQueryName(): string
    {
        return $this->queryName;
    }

    public function getRowsCount(): int
    {
        return $this->rowsCount;
    }

    public function getSender(): CommandSender
    {
        return $this->sender;
    }

    public function getCommandData(): CommandData
    {
        return $this->commandData;
    }

    public function getPosition(): Vector3
    {
        return $this->position;
    }
}