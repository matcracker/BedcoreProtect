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

use InvalidArgumentException;
use matcracker\BedcoreProtect\Main;
use function array_keys;
use function count;
use function ucfirst;

final class ConfigUpdater
{
    public const LAST_VERSION = 4;
    private const KEY_NOT_PRESENT = -1;

    public function __construct(private Main $plugin)
    {
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
        $resultOptions = $this->iterateConfigurations($oldConfigData, $newConfigData);

        $this->plugin->getConfig()->setAll($newConfigData);

        $this->plugin->getLogger()->info("The configuration file has been updated.");
        $this->printOptions($resultOptions, "new");
        $this->printOptions($resultOptions, "changed");
        $this->printOptions($resultOptions, "removed");

        try {
            $this->plugin->getConfig()->save();
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
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

    /**
     * Return the number of options changed between the old and the new configurations.
     *
     * @param array $oldConfigData
     * @param array $newConfigData
     * @return string[][]
     */
    private function iterateConfigurations(array $oldConfigData, array &$newConfigData): array
    {
        static $skipKeys = [
            "config-version"
        ];

        $resultOptions = [
            "new" => array_keys(array_diff_key($newConfigData, $oldConfigData)),
            "removed" => array_keys(array_diff_key($oldConfigData, $newConfigData))
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
                    $resultOptions["changed"][] = $key;
                    continue;
                }

                //Nested values
                if (is_array($value)) {
                    $resultOptions = array_merge_recursive($resultOptions, $this->iterateConfigurations($oldConfigData[$key], $value));
                } else {
                    $value = $oldValue;
                }
            }
        }

        return $resultOptions;
    }

    private function printOptions(array $resultOptions, string $type): void
    {
        if (isset($resultOptions[$type])) {
            if (count($resultOptions[$type]) > 0) {
                $this->plugin->getLogger()->info(ucfirst($type) . " options:");
                foreach ($resultOptions[$type] as $option) {
                    $this->plugin->getLogger()->info("- $option");
                }
            }
        }
    }
}
