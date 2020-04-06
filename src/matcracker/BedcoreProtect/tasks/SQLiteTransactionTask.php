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

namespace matcracker\BedcoreProtect\tasks;

use matcracker\BedcoreProtect\storage\queries\PluginQueries;
use pocketmine\scheduler\Task;

final class SQLiteTransactionTask extends Task
{
    /** @var PluginQueries */
    private $pluginQueries;

    public function __construct(PluginQueries $pluginQueries)
    {
        $this->pluginQueries = $pluginQueries;
    }

    /**
     * Return the ticks when task is executed
     * @return int
     */
    final public static function getTicks(): int
    {
        //Default auto-save world ticks (5 minutes)
        return 6000;
    }

    public function onRun(int $currentTick): void
    {
        $this->pluginQueries->storeTransaction();
    }

    public function onCancel(): void
    {
        $this->pluginQueries->endTransaction();
    }
}
