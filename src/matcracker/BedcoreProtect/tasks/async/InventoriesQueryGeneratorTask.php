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

use matcracker\BedcoreProtect\serializable\SerializableItem;
use function mb_substr;

final class InventoriesQueryGeneratorTask extends QueryGeneratorTask
{
    /** @var SerializableItem[] */
    private $items;

    /**
     * InventoriesQueryGen constructor.
     * @param int $firstInsertedId
     * @param SerializableItem[] $items
     * @param callable|null $onComplete
     */
    public function __construct(int $firstInsertedId, array $items, ?callable $onComplete)
    {
        parent::__construct($firstInsertedId, $onComplete);
        $this->items = $items;
    }

    public function onRun(): void
    {
        $query = /**@lang text */
            'INSERT INTO inventories_log(history_id, slot, old_id, old_meta, old_nbt, old_amount) VALUES';

        foreach ($this->items as $slot => $item) {
            $query .= "('{$this->logId}', '{$slot}', '{$item->getId()}', '{$item->getMeta()}', '{$item->getSerializedNbt()}', '{$item->getCount()}'),";
            $this->logId++;
        }

        $query = mb_substr($query, 0, -1) . ';';
        $this->setResult($query);
    }
}
