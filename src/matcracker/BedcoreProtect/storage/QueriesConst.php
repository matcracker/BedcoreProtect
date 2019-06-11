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

namespace matcracker\BedcoreProtect\storage;

final class QueriesConst
{
    //Blocks
    public const PLACED = 0;
    public const BROKE = 1;
    public const CLICKED = 2;
    //Entity
    public const KILLED = 3;
    //Inventory
    public const ADDED = 4;
    public const REMOVED = 5;

    public const INIT_ENTITY = "bcp.table.entities";
    public const INIT_BLOCKS = "bcp.table.blocks";
    public const INIT_LOG_HISTORY = "bcp.table.log_history";
    public const INIT_BLOCKS_LOG = "bcp.table.blocks_log";
    public const INIT_ENTITIES_LOG = "bcp.table.entities_log";
    public const INIT_SIGNS_LOG = "bcp.table.signs_log";
    public const INIT_INVENTORIES_LOG = "bcp.table.inventories_log";
    public const INIT_TABLES = [
        self::INIT_ENTITY, self::INIT_BLOCKS,
        self::INIT_LOG_HISTORY, self::INIT_BLOCKS_LOG,
        self::INIT_ENTITIES_LOG, self::INIT_SIGNS_LOG,
        self::INIT_INVENTORIES_LOG
    ];
    public const ADD_ENTITY = "bcp.add.entity";
    public const ADD_BLOCK = "bcp.add.block";
    public const ADD_HISTORY_LOG = "bcp.add.log.main";
    public const ADD_BLOCK_LOG = "bcp.add.log.to_block";
    public const ADD_ENTITY_LOG = "bcp.add.log.to_entity";
    public const ADD_SIGN_LOG = "bcp.add.log.to_sign";
    public const ADD_INVENTORY_LOG = "bcp.add.log.to_inventory";
    public const GET_LAST_LOG_ID = "bcp.get.log.last_id";
    public const GET_SIGN_LOG = "bcp.get.log.sign";
    public const GET_BLOCK_LOG = "bcp.get.log.block";
    public const GET_ENTITY_LOG = "bcp.get.log.entity";
    public const GET_NEAR_LOG = "bcp.get.log.near";
    public const GET_TRANSACTION_LOG = "bcp.get.log.transaction";
    public const PURGE = "bcp.purge";

    private function __construct()
    {
    }
}