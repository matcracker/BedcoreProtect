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

use BadMethodCallException;
use pocketmine\level\Level;
use pocketmine\utils\Config;

/**
 * It parses the plugin configuration to be an object.
 *
 * Class ConfigParser
 * @package matcracker\BedcoreProtect\utils
 */
final class ConfigParser
{
    /**@var Config $config */
    private $config;

    /**@var array $data */
    private $data = [];

    /**@var bool $isValid */
    private $isValid = false;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getLanguage(): string
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (string)$this->data['language'];
    }

    public function getPrintableDatabaseType(): string
    {
        return $this->isSQLite() ? 'SQLite' : 'MySQL';
    }

    public function isSQLite(): bool
    {
        return $this->getDatabaseType() === 'sqlite';
    }

    public function getDatabaseType(): string
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (string)$this->data['database']['type'];
    }

    public function isEnabledWorld(Level $world): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return in_array($world->getName(), $this->getEnabledWorlds()) || empty($this->getEnabledWorlds());
    }

    public function getEnabledWorlds(): array
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (array)$this->data['enabled-worlds'];
    }

    public function getCheckUpdates(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['check-updates'];
    }

    public function getDefaultRadius(): int
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (int)$this->data['default-radius'];
    }

    public function getMaxRadius(): int
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (int)$this->data['max-radius'];
    }

    public function getRollbackItems(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException("The configuration must be validated.");
        }

        return (bool)$this->data['rollback-items'];
    }

    public function getRollbackEntities(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['rollback-entities'];
    }

    public function getBlockPlace(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['block-place'];
    }

    public function getBlockBreak(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['block-break'];
    }

    public function getNaturalBreak(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['natural-break'];
    }

    public function getBlockMovement(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['block-movement'];
    }

    public function getBlockBurn(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['block-burn'];
    }

    public function getExplosions(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['explosions'];
    }

    public function getEntityKills(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['entity-kills'];
    }

    public function getSignText(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['sign-text'];
    }

    public function getBuckets(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['buckets'];
    }

    public function getLeavesDecay(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['leaves-decay'];
    }

    public function getLiquidTracking(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['liquid-tracking'];
    }

    public function getItemTransactions(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['item-transactions'];
    }

    public function getPlayerInteractions(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (bool)$this->data['player-interactions'];
    }

    public function getBlockSniperHook(): bool
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }
        return (bool)$this->data['blocksniper-hook'];
    }

    public function isValidConfig(): bool
    {
        return $this->isValid;
    }

    public function validate(): self
    {
        $data = $this->config->getAll();

        $this->isValid = true;
        $this->data = $data;

        date_default_timezone_set($this->getTimezone());
        return $this;
    }

    public function getTimezone(): string
    {
        if (!$this->isValid) {
            throw new BadMethodCallException('The configuration must be validated.');
        }

        return (string)$this->data['timezone'];
    }
}