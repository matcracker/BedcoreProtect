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

namespace matcracker\BedcoreProtect\config;

use pocketmine\utils\Config;
use pocketmine\World\World;
use poggit\libasynql\SqlDialect;
use function count;
use function in_array;

/**
 * It parses the plugin configuration to be an object.
 *
 * Class ConfigParser
 * @package matcracker\BedcoreProtect\utils
 */
final class ConfigParser
{
    private array $data;

    public function __construct(Config $config)
    {
        $this->data = $config->getAll();

        //TODO: implement config. validator (PM4)
    }

    public function getLanguage(): string
    {
        return (string)$this->data["language"];
    }

    public function getPrintableDatabaseType(): string
    {
        return $this->isSQLite() ? "SQLite" : "MySQL";
    }

    public function isSQLite(): bool
    {
        return $this->getDatabaseType() === SqlDialect::SQLITE;
    }

    public function getDatabaseType(): string
    {
        return (string)$this->data["database"]["type"];
    }

    /**
     * Return the database file name for SQLite.
     */
    public function getDatabaseFileName(): string
    {
        return (string)$this->data["database"]["sqlite"]["file"];
    }

    public function isEnabledUI(): bool
    {
        return (bool)$this->data["enable-ui-menu"];
    }

    public function isEnabledWorld(World $world): bool
    {
        return in_array($world->getFolderName(), $this->getEnabledWorlds()) || count($this->getEnabledWorlds()) === 0;
    }

    public function getEnabledWorlds(): array
    {
        return (array)$this->data["enabled-worlds"];
    }

    public function getCheckUpdates(): bool
    {
        return (bool)$this->data["check-updates"];
    }

    public function getDefaultRadius(): int
    {
        return (int)$this->data["default-radius"];
    }

    public function getMaxRadius(): int
    {
        return (int)$this->data["max-radius"];
    }

    public function getRollbackItems(): bool
    {
        return (bool)$this->data["rollback-items"];
    }

    public function getRollbackEntities(): bool
    {
        return (bool)$this->data["rollback-entities"];
    }

    public function getBlockPlace(): bool
    {
        return (bool)$this->data["block-place"];
    }

    public function getBlockBreak(): bool
    {
        return (bool)$this->data["block-break"];
    }

    public function getNaturalBreak(): bool
    {
        return (bool)$this->data["natural-break"];
    }

    public function getBlockMovement(): bool
    {
        return (bool)$this->data["block-movement"];
    }

    public function getBlockBurn(): bool
    {
        return (bool)$this->data["block-burn"];
    }

    public function getExplosions(): bool
    {
        return (bool)$this->data["explosions"];
    }

    public function getEntityKills(): bool
    {
        return (bool)$this->data["entity-kills"];
    }

    public function getSignText(): bool
    {
        return (bool)$this->data["sign-text"];
    }

    public function getBuckets(): bool
    {
        return (bool)$this->data["buckets"];
    }

    public function getLeavesDecay(): bool
    {
        return (bool)$this->data["leaves-decay"];
    }

    public function getLiquidTracking(): bool
    {
        return (bool)$this->data["liquid-tracking"];
    }

    public function getItemTransactions(): bool
    {
        return (bool)$this->data["item-transactions"];
    }

    public function getPlayerInteractions(): bool
    {
        return (bool)$this->data["player-interactions"];
    }

    public function getDebugMode(): bool
    {
        return (bool)$this->data["debug-mode"];
    }
}
