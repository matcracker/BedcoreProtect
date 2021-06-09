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

use matcracker\BedcoreProtect\commands\CommandParser;
use pocketmine\math\AxisAlignedBB;

final class UndoRollbackData
{
    /** @var bool */
    private $rollback;
    /** @var AxisAlignedBB */
    private $bb;
    /** @var CommandParser */
    private $commandParser;
    /** @var int[] */
    private $logIds;

    /**
     * UndoRollbackData constructor.
     * @param bool $rollback
     * @param AxisAlignedBB $bb
     * @param CommandParser $commandParser
     * @param int[] $logIds
     */
    public function __construct(bool $rollback, AxisAlignedBB $bb, CommandParser $commandParser, array $logIds)
    {
        $this->rollback = !$rollback;
        $this->bb = $bb;
        $this->commandParser = $commandParser;
        $this->logIds = $logIds;
    }

    public function isRollback(): bool
    {
        return $this->rollback;
    }

    public function getBoundingBox(): AxisAlignedBB
    {
        return $this->bb;
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
