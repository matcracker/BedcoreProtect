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

use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\math\Area;

final class UndoRollbackData
{
    /** @var bool */
    private $rollback;
    /** @var Area */
    private $area;
    /** @var CommandParser */
    private $commandParser;
    /** @var int[] */
    private $logIds;

    /**
     * UndoRollbackData constructor.
     * @param bool $rollback
     * @param Area $area
     * @param CommandParser $commandParser
     * @param int[] $logIds
     */
    public function __construct(bool $rollback, Area $area, CommandParser $commandParser, array $logIds)
    {
        $this->rollback = !$rollback;
        $this->area = $area;
        $this->commandParser = $commandParser;
        $this->logIds = $logIds;
    }

    public function isRollback(): bool
    {
        return $this->rollback;
    }

    public function getArea(): Area
    {
        return $this->area;
    }

    public function getCommandParser(): CommandParser
    {
        return $this->commandParser;
    }

    /**
     * @return int[]
     */
    public function getLogIds(): array
    {
        return $this->logIds;
    }
}
