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
use pocketmine\math\AxisAlignedBB;

final class UndoRollbackData
{
    private bool $rollback;
    private AxisAlignedBB $bb;
    private CommandData $commandData;
    /** @var int[] */
    private array $logIds;

    /**
     * UndoRollbackData constructor.
     * @param bool $rollback
     * @param AxisAlignedBB $bb
     * @param CommandData $commandData
     * @param int[] $logIds
     */
    public function __construct(bool $rollback, AxisAlignedBB $bb, CommandData $commandData, array $logIds)
    {
        $this->rollback = !$rollback;
        $this->bb = $bb;
        $this->commandData = $commandData;
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

    public function getCommandData(): CommandData
    {
        return $this->commandData;
    }

    /**
     * @return int[]
     */
    public function getLogIds(): array
    {
        return $this->logIds;
    }
}
