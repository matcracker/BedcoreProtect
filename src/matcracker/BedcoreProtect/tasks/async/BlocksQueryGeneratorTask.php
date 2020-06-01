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
use function is_array;
use function mb_substr;

final class BlocksQueryGeneratorTask extends QueryGeneratorTask
{
    /** @var string */
    private $oldBlocks;
    /** @var string */
    private $newBlocks;

    /**
     * BlocksQueryGeneratorTask constructor.
     * @param int $firstInsertedId
     * @param SerializableBlock[] $oldBlocks
     * @param SerializableBlock[]|SerializableBlock $newBlocks
     * @param callable|null $onComplete
     */
    public function __construct(int $firstInsertedId, array $oldBlocks, $newBlocks, ?callable $onComplete)
    {
        parent::__construct($firstInsertedId, $onComplete);
        $this->oldBlocks = $oldBlocks;
        $this->newBlocks = $newBlocks;
    }

    public function onRun(): void
    {
        $query = /**@lang text */
            "INSERT INTO blocks_log(history_id, old_id, old_meta, old_nbt, new_id, new_meta, new_nbt) VALUES";

        if (!is_array($this->newBlocks)) {
            $newId = $this->newBlocks->getId();
            $newMeta = $this->newBlocks->getMeta();
            $newNBT = $this->newBlocks->getSerializedNbt();

            foreach ($this->oldBlocks as $oldBlock) {
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();
                $query .= "('{$this->logId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
                $this->logId++;
            }
        } else {
            if (count($this->oldBlocks) !== count($this->newBlocks)) {
                throw new ArrayOutOfBoundsException('The number of old blocks must be the same as new blocks, or vice-versa');
            }

            foreach ($this->oldBlocks as $key => $oldBlock) {
                $newBlock = $this->newBlocks[$key];
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();
                $newId = $newBlock->getId();
                $newMeta = $newBlock->getMeta();
                $newNBT = $newBlock->getSerializedNbt();

                $query .= "('{$this->logId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
                $this->logId++;
            }
        }
        $query = mb_substr($query, 0, -1) . ';';

        $this->setResult($query);
    }
}
