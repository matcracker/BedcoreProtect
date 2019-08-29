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
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use poggit\libasynql\DataConnector;

class AsyncBlocksQueryGenerator extends AsyncTask
{
    /**@var int $lastLogId */
    private $lastLogId;
    /**@var string $oldBlocks */
    private $oldBlocks;
    /**@var string $newBlocks */
    private $newBlocks;

    /**
     * AsyncBlocksQueryGenerator constructor.
     * @param DataConnector $connector
     * @param int $lastLogId
     * @param SerializableBlock[] $oldBlocks
     * @param SerializableBlock[]|SerializableBlock $newBlocks
     */
    public function __construct(DataConnector $connector, int $lastLogId, array $oldBlocks, $newBlocks)
    {
        $this->storeLocal($connector);
        $this->lastLogId = $lastLogId;
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
                $this->lastLogId++;
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();
                $query .= "('{$this->lastLogId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
            }
        } else {
            if (count($oldBlocks) !== count($newBlocks)) {
                throw new ArrayOutOfBoundsException('The number of old blocks must be the same as new blocks, or vice-versa');
            }

            foreach ($oldBlocks as $key => $oldBlock) {
                $this->lastLogId++;
                $newBlock = $newBlocks[$key];
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();
                $newId = $newBlock->getId();
                $newMeta = $newBlock->getMeta();
                $newNBT = $newBlock->getSerializedNbt();

                $query .= "('{$this->lastLogId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
            }
        }
        $query = mb_substr($query, 0, -1) . ';';

        $this->setResult($query);
    }

    public function onCompletion(Server $server): void
    {
        /**@var Main $plugin */
        $plugin = Server::getInstance()->getPluginManager()->getPlugin(Main::PLUGIN_NAME);
        if ($plugin === null) {
            return;
        }
        /**@var DataConnector $connector */
        $connector = $this->fetchLocal();
        $connector->executeInsertRaw((string)$this->getResult());
    }
}