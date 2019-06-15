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

namespace matcracker\BedcoreProtect\matcracker\BedcoreProtect\listeners;

use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\Main;
use pocketmine\event\level\LevelSaveEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

final class PluginListener implements Listener
{
    private $plugin;
    private $database;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->database = $plugin->getDatabase();
    }

    /**
     * @param LevelSaveEvent $event
     * @priority HIGH
     */
    public function onLevelSave(LevelSaveEvent $event): void
    {
        $this->database->getQueries()->endTransaction();
        $this->database->getQueries()->beginTransaction();
    }

    /**
     * @param PlayerJoinEvent $event
     * @priority LOWEST
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $this->database->getQueries()->addEntity($event->getPlayer());
    }

    /**
     * @param PlayerQuitEvent $event
     * @priority LOWEST
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        Inspector::removeInspector($event->getPlayer());
    }
}