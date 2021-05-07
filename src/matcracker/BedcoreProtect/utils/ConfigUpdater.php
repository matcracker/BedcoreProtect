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
use function date;
use function gettype;
use function in_array;
use function is_array;
use function is_string;
use function rename;
use const DIRECTORY_SEPARATOR;

final class ConfigUpdater
{
    public const LAST_VERSION = 2;
    private const KEY_NOT_PRESENT = -1;

    /** @var Main */
    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function checkUpdate(): bool
    {
        $confVersion = (int)$this->plugin->getConfig()->get("config-version", self::KEY_NOT_PRESENT);

        return $confVersion !== self::LAST_VERSION;
    }

    public function update(): bool
    {
        //Get a copy of previous configuration
        $oldConfigData = $this->plugin->getConfig()->getAll();

        //Create a backup of old configuration
        if (!$this->saveConfigBackup()) {
            return false;
        }

        $newConfigData = $this->plugin->getConfig()->getAll();
        //Update the new configuration with the previous one.
        $this->iterateConfigurations($oldConfigData, $newConfigData);

        $this->plugin->getConfig()->setAll($newConfigData);

        return $this->plugin->getConfig()->save();
    }

    private function saveConfigBackup(): bool
    {
        $path = $this->plugin->getConfig()->getPath();
        $date = date("Y_m_d-H_i_s");

        if (!is_string($date)) {
            return false;
        }

        if (!rename($path, $this->plugin->getDataFolder() . DIRECTORY_SEPARATOR . "config-" . $date . ".yml")) {
            return false;
        }

        $this->plugin->reloadConfig();
        return true;
    }

    private function iterateConfigurations(array $oldConfigData, array &$newConfigData): void
    {
        static $skipKeys = [
            "config-version"
        ];

        foreach ($newConfigData as $key => &$value) {
            if (in_array($key, $skipKeys)) {
                continue;
            }

            //Check if the same key is present in both configuration to try to maintain the value.
            if (isset($oldConfigData[$key])) {
                $oldValue = $oldConfigData[$key];

                /*
                 * If the values types are different, it means that the structure is changed
                 * so we need to ignore it.
                 */
                if (gettype($oldValue) !== gettype($value)) {
                    continue;
                }

                //Nested values
                if (is_array($value)) {
                    $this->iterateConfigurations($oldConfigData[$key], $value);
                } else {
                    $value = $oldValue;
                }
            }
        }
    }
}
