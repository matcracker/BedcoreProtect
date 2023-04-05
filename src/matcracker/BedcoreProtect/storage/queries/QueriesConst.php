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

namespace matcracker\BedcoreProtect\storage\queries;

abstract class QueriesConst
{
    public const INIT_TABLES = "bcp.table.init";
    public const SET_FOREIGN_KEYS = "bcp.generic.set_foreign_keys";
    public const BEGIN_TRANSACTION = "bcp.generic.begin_transaction";
    public const END_TRANSACTION = "bcp.generic.end_transaction";
    public const OPTIMIZE = "bcp.generic.optimize";
    public const VACUUM = "bcp.generic.vacuum";
    public const ADD_ENTITY = "bcp.add.entity";
    public const ADD_DATABASE_VERSION = "bcp.add.db_version";
    public const ADD_HISTORY_LOG = "bcp.add.log.main";
    public const ADD_BLOCK_LOG = "bcp.add.log.block";
    public const ADD_ENTITY_LOG = "bcp.add.log.entity";
    public const ADD_INVENTORY_LOG = "bcp.add.log.inventory";
    public const UPDATE_ENTITY_ID = "bcp.update.entity_id";
    public const UPDATE_ROLLBACK_STATUS = "bcp.update.rollback_status";
    public const GET_DATABASE_STATUS = "bcp.get.db_status";
    public const GET_ROLLBACK_OLD_BLOCKS = "bcp.get.log.old_blocks";
    public const GET_ROLLBACK_NEW_BLOCKS = "bcp.get.log.new_blocks";
    public const GET_ROLLBACK_OLD_INVENTORIES = "bcp.get.log.old_inventories";
    public const GET_ROLLBACK_NEW_INVENTORIES = "bcp.get.log.new_inventories";
    public const GET_ROLLBACK_ENTITIES = "bcp.get.log.entities";
    public const GET_BLOCK_LOG = "bcp.get.log.block";
    public const GET_ENTITY_LOG = "bcp.get.log.entity";
    public const GET_NEAR_LOG = "bcp.get.log.near";
    public const GET_TRANSACTION_LOG = "bcp.get.log.transaction";
    public const PURGE_TIME = "bcp.purge.time";
    public const PURGE_WORLD = "bcp.purge.world";

    public static function VERSION_PATCH(string $version, int $patchNumber): string
    {
        return "patch.$version.$patchNumber";
    }
}
