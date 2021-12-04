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

namespace matcracker\BedcoreProtect\listeners;

use matcracker\BedcoreProtect\config\ConfigParser;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\BlocksQueries;
use matcracker\BedcoreProtect\storage\queries\EntitiesQueries;
use matcracker\BedcoreProtect\storage\queries\InventoriesQueries;
use matcracker\BedcoreProtect\storage\queries\PluginQueries;
use pocketmine\event\Listener;

abstract class BedcoreListener implements Listener
{
    public ConfigParser $config;
    protected PluginQueries $pluginQueries;
    protected BlocksQueries $blocksQueries;
    protected EntitiesQueries $entitiesQueries;
    protected InventoriesQueries $inventoriesQueries;

    public function __construct(
        protected Main $plugin
    )
    {
        $this->config = $plugin->getParsedConfig();
        $this->pluginQueries = $plugin->getDatabase()->getQueryManager()->getPluginQueries();
        $this->blocksQueries = $plugin->getDatabase()->getQueryManager()->getBlocksQueries();
        $this->entitiesQueries = $plugin->getDatabase()->getQueryManager()->getEntitiesQueries();
        $this->inventoriesQueries = $plugin->getDatabase()->getQueryManager()->getInventoriesQueries();
    }
}
