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

namespace matcracker\BedcoreProtect\storage;

use matcracker\BedcoreProtect\commands\CommandData;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\World\Position;

class LookupData
{
    public const NEAR_LOG = 0;
    public const BLOCK_LOG = 1;
    public const TRANSACTION_LOG = 2;
    public const LOOKUP_LOG = 3;

    /** @var self[] */
    private static array $lookupData = [];

    public function __construct(
        private int            $queryType,
        private int            $rows,
        private ?CommandSender $sender,
        private ?CommandData   $commandData,
        private ?Position      $position)
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

    public function getQueryType(): int
    {
        return $this->queryType;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getSender(): ?CommandSender
    {
        return $this->sender;
    }

    public function getCommandData(): ?CommandData
    {
        return $this->commandData;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }
}