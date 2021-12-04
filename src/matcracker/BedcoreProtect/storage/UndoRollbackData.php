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

final class UndoRollbackData
{
    /**
     * UndoRollbackData constructor.
     * @param bool $rollback
     * @param CommandData $commandData
     * @param float $startTime
     */
    public function __construct(
        private bool        $rollback,
        private CommandData $commandData,
        private float       $startTime)
    {
        $this->rollback = !$rollback;
    }

    public function isRollback(): bool
    {
        return $this->rollback;
    }

    public function getCommandData(): CommandData
    {
        return $this->commandData;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }
}
