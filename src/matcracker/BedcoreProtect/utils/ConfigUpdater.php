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
use function is_string;
use function rename;
use const DIRECTORY_SEPARATOR;

final class ConfigUpdater
{
    private const KEY_NOT_PRESENT = -1;
    public const LAST_VERSION = 1;

    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function checkUpdate(): void
    {
        $config = $this->plugin->getConfig();
        $confVersion = (int)$config->get("config-version", self::KEY_NOT_PRESENT);

        if ($confVersion === self::LAST_VERSION) {
            return;
        }

        if (!$this->saveConfigBackup()) {
            $this->plugin->getLogger()->critical($this->plugin->getLanguage()->translateString("config.updater.save-error"));
            $this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
            return;
        }

        $this->plugin->getLogger()->critical($this->plugin->getLanguage()->translateString("config.updater.outdated"));
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

        return $this->plugin->saveDefaultConfig();
    }
}
