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

namespace matcracker\BedcoreProtect\tasks\async;

use ArrayOutOfBoundsException;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use function count;
use function mb_substr;

final class BlocksQueryGeneratorTask extends QueryGeneratorTask
{
    /** @var SerializableBlock[] */
    private $oldBlocks;
    /** @var SerializableBlock[] */
    private $newBlocks;

    /**
     * BlocksQueryGeneratorTask constructor.
     * @param int $firstInsertedId
     * @param SerializableBlock[] $oldBlocks
     * @param SerializableBlock[] $newBlocks
     * @param callable|null $onComplete
     */
    public function __construct(int $firstInsertedId, array $oldBlocks, array $newBlocks, ?callable $onComplete)
    {
        parent::__construct($firstInsertedId, $onComplete);
        $this->oldBlocks = $oldBlocks;
        $this->newBlocks = $newBlocks;
    }

    public function onRun(): void
    {
        $query = /**@lang text */
            "INSERT INTO blocks_log(history_id, old_id, old_meta, old_nbt, new_id, new_meta, new_nbt) VALUES";

        if (count($this->newBlocks) === 1) {
            $newId = $this->newBlocks[0]->getId();
            $newMeta = $this->newBlocks[0]->getMeta();
            $newNBT = $this->newBlocks[0]->getSerializedNbt();

            foreach ($this->oldBlocks as $key => $oldBlock) {
                $newBlock = $newBlock ?? $this->newBlocks[$key];
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();

                $query .= "('{$this->logId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
                $this->logId++;
            }

        } elseif (($oldC = count($this->oldBlocks)) === ($newC = count($this->newBlocks))) {
            foreach ($this->oldBlocks as $key => $oldBlock) {
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();

                $newId = $this->newBlocks[$key]->getId();
                $newMeta = $this->newBlocks[$key]->getMeta();
                $newNBT = $this->newBlocks[$key]->getSerializedNbt();

                $query .= "('{$this->logId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
                $this->logId++;
            }
        } else {
            throw new ArrayOutOfBoundsException("The number of old blocks must be the same as new blocks, or vice-versa. Got {$oldC} !== {$newC}");
        }

        $query = mb_substr($query, 0, -1) . ';';

        $this->setResult($query);
    }
}
