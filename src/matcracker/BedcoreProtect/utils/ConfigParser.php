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

use matcracker\BedcoreProtect\Main;
use Particle\Validator\ValidationResult;
use Particle\Validator\Validator;

/**
 * It parses the plugin configuration to be an object.
 *
 * Class ConfigParser
 * @package matcracker\BedcoreProtect\utils
 */
final class ConfigParser
{
    private $data;

    public function __construct(Main $main)
    {
        $this->data = $main->getConfig()->getAll();
    }

    public function isSQLite(): bool
    {
        return $this->getDatabaseType() === "sqlite";
    }

    public function getDatabaseType(): string
    {
        return (string)$this->data['database']['type'];
    }

    public function getBlockPlace(): bool
    {
        return (bool)$this->data['block-place'];
    }

    public function getBlockBreak(): bool
    {
        return (bool)$this->data['block-break'];
    }

    public function getDefaultRadius(): int
    {
        return (int)$this->data['default-radius'];
    }

    public function getMaxRadius(): int
    {
        return (int)$this->data['max-radius'];
    }

    public function getExplosions(): bool
    {
        return (bool)$this->data['explosions'];
    }

    public function getEntityKills(): bool
    {
        return (bool)$this->data['entity-kills'];
    }

    public function getSignText(): bool
    {
        return (bool)$this->data['sign-text'];
    }

    public function getBuckets(): bool
    {
        return (bool)$this->data['buckets'];
    }

    public function getItemTransactions(): bool
    {
        return (bool)$this->data['item-transactions'];
    }

    public function getPlayerInteractions(): bool
    {
        return (bool)$this->data['player-interactions'];
    }

    public function validateConfig(): ValidationResult
    {
        $v = new Validator();

        $v->required('database.type')->string()->callback(function (string $value) {
            return $value === 'sqlite' || $value === 'mysql';
        });
        $v->required('database.sqlite.file')->string();
        //TODO: Check if mysql
        $v->required('database.mysql.host')->string()->callback(function (string $value) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        });
        $v->required('database.mysql.username')->string();
        $v->required('database.mysql.password')->string()->allowEmpty(true);
        $v->required('database.mysql.schema')->string();
        $v->required('database.worker-limit')->integer()->between(1, PHP_INT_MAX);
        $v->required('check-updates')->bool();
        $v->required('default-radius')->integer()->between(0, PHP_INT_MAX);
        $v->required('max-radius')->integer()->between(0, PHP_INT_MAX);

        foreach (array_slice(array_keys($this->data), 4) as $key) {
            $v->required($key)->bool();
        }

        return $v->validate($this->data);
    }

}