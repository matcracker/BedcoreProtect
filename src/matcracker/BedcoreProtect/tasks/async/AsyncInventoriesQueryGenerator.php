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

use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\serializable\SerializableItem;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class AsyncInventoriesQueryGenerator extends AsyncTask
{
    private $lastLogId;
    private $items;

    /**
     * AsyncInventoriesQueryGenerator constructor.
     * @param int $lastLogId
     * @param SerializableItem[] $items
     */
    public function __construct(int $lastLogId, array $items)
    {
        $this->lastLogId = $lastLogId;
        $this->items = $items;
    }

    public function onRun(): void
    {
        $query = /**@lang text */
            "INSERT INTO inventories_log(history_id, slot, old_item_id, old_item_meta, old_item_nbt, old_item_amount) VALUES";

        foreach ($this->items as $slot => $item) {
            $query .= "('{$this->lastLogId}', '{$slot}', '{$item->getId()}', '{$item->getMeta()}', '{$item->getSerializedNbt()}', '{$item->getCount()}'),";
            $this->lastLogId++;
        }

        $query = mb_substr($query, 0, -1) . ";";
        $this->setResult($query);
    }

    public function onCompletion(Server $server): void
    {
        /**@var Main $plugin */
        $plugin = $server->getPluginManager()->getPlugin(Main::PLUGIN_NAME);
        if ($plugin === null) {
            return;
        }
        $plugin->getDatabase()->getQueries()->insertRaw((string)$this->getResult());
    }
}