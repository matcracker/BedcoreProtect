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
use function serialize;
use function unserialize;

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
        $this->oldBlocks = serialize($oldBlocks);
        $this->newBlocks = serialize($newBlocks);
    }

    public function onRun(): void
    {
        $query = /**@lang text */
            "INSERT INTO blocks_log(history_id, old_id, old_meta, old_nbt, new_id, new_meta, new_nbt) VALUES";

        /** @var SerializableBlock[] $oldBlocks */
        $oldBlocks = unserialize($this->oldBlocks);
        /** @var SerializableBlock[]|SerializableBlock $newBlocks */
        $newBlocks = unserialize($this->newBlocks);

        if (!is_array($newBlocks)) {
            $newId = $newBlocks->getId();
            $newMeta = $newBlocks->getMeta();
            $newNBT = $newBlocks->getSerializedNbt();

            foreach ($oldBlocks as $oldBlock) {
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();
                $query .= "('{$this->logId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
                $this->logId++;
            }
        } else {
            if (count($oldBlocks) !== count($newBlocks)) {
                throw new ArrayOutOfBoundsException('The number of old blocks must be the same as new blocks, or vice-versa');
            }

            foreach ($oldBlocks as $key => $oldBlock) {
                $newBlock = $newBlocks[$key];
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
