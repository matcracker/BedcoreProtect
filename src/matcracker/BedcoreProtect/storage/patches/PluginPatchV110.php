<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2023
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

namespace matcracker\BedcoreProtect\storage\patches;

use Closure;
use Generator;
use matcracker\BedcoreProtect\storage\DataConnectorHelper;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\block\convert\UnsupportedBlockStateException;
use pocketmine\data\bedrock\item\ItemTypeSerializeException;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\VanillaItems;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\SqlDialect;
use function array_merge;
use function count;

final class PluginPatchV110 extends PluginPatch
{
    private const SELECT_LIMIT = 10000;

    public function getVersion(): string
    {
        return "1.1.0";
    }

    public function asyncPatchSQLite(int $patchId): Generator
    {
        match ($patchId) {
            1 => yield from $this->upgradeBlocksTable(SqlDialect::SQLITE),
            4 => yield from $this->upgradeInventoryTable(SqlDialect::SQLITE),
            default => 0 && yield
        };
    }

    public function asyncPatchMySQL(int $patchId): Generator
    {
        match ($patchId) {
            1 => yield from $this->upgradeBlocksTable(SqlDialect::MYSQL),
            5 => yield from $this->upgradeInventoryTable(SqlDialect::MYSQL),
            default => 0 && yield
        };
    }

    private function upgradeBlocksTable(string $dialect): Generator
    {
        $offset = 0;
        /** @var array[] $rows */
        while (count($rows = yield from DataConnectorHelper::asyncSelectRaw($this->connector, /** @lang text */ "SELECT * FROM blocks_log LIMIT " . self::SELECT_LIMIT . " OFFSET $offset;")) > 0) {
            yield from $this->connector->asyncChange(QueriesConst::BEGIN_TRANSACTION);
            yield from $this->upgradeBlocks($dialect, static fn() => $rows);
            yield from $this->connector->asyncChange(QueriesConst::END_TRANSACTION);

            $offset += self::SELECT_LIMIT;
        }
    }

    private function upgradeBlocks(string $dialect, Closure $onPatch): Generator
    {
        /** @var array[] $rows */
        $rows = $onPatch();

        $deserializer = GlobalBlockStateHandlers::getDeserializer();
        $upgrader = GlobalBlockStateHandlers::getUpgrader();

        /** @var string[] $queries */
        $queries = [];
        $args = [];

        $variables = [
            "history_id" => new GenericVariable("history_id", GenericVariable::TYPE_INT, null),
            "old_name" => new GenericVariable("old_name", GenericVariable::TYPE_STRING, null),
            "old_state" => new GenericVariable("old_state", GenericVariable::TYPE_STRING, null),
            "old_nbt" => new GenericVariable("old_nbt", "?" . GenericVariable::TYPE_STRING, null),
            "new_name" => new GenericVariable("new_name", GenericVariable::TYPE_STRING, null),
            "new_state" => new GenericVariable("new_state", GenericVariable::TYPE_STRING, null),
            "new_nbt" => new GenericVariable("new_nbt", "?" . GenericVariable::TYPE_STRING, null)
        ];

        foreach ($rows as $row) {
            $logId = (int)$row["history_id"];

            $oldId = (int)$row["old_id"];
            $oldMeta = (int)$row["old_meta"];

            try {
                $oldState = $upgrader->upgradeIntIdMeta($oldId, $oldMeta);
            } catch (BlockStateDeserializeException) {
                $this->getLogger()->debug("Could not upgrade old legacy block $oldId:$oldMeta");
                yield from $this->connector->asyncChange(QueriesConst::PURGE_ID, ["log_id" => $logId]);
                continue;
            }

            try {
                $oldBlock = $deserializer->deserializeBlock($oldState);
            } catch (UnsupportedBlockStateException) {
                $this->getLogger()->debug("Found unregistered old block \"{$oldState->getName()}\"");
                yield from $this->connector->asyncChange(QueriesConst::PURGE_ID, ["log_id" => $logId]);
                continue;
            }

            $newId = (int)$row["new_id"];
            $newMeta = (int)$row["new_meta"];
            try {
                $newState = $upgrader->upgradeIntIdMeta($newId, $newMeta);
            } catch (BlockStateDeserializeException) {
                $this->getLogger()->debug("Could not upgrade new legacy block $newId:$newMeta");
                yield from $this->connector->asyncChange(QueriesConst::PURGE_ID, ["log_id" => $logId]);
                continue;
            }

            try {
                $newBlock = $deserializer->deserializeBlock($newState);
            } catch (UnsupportedBlockStateException) {
                $this->getLogger()->debug("Found unregistered new block \"{$newState->getName()}\"");
                yield from $this->connector->asyncChange(QueriesConst::PURGE_ID, ["log_id" => $logId]);
                continue;
            }

            $parameters = [
                "history_id" => $logId,
                "old_name" => $oldBlock->getName(),
                "old_state" => Utils::serializeNBT($oldState->toNbt()),
                "old_nbt" => $row["old_nbt"],
                "new_name" => $newBlock->getName(),
                "new_state" => Utils::serializeNBT($newState->toNbt()),
                "new_nbt" => $row["new_nbt"]
            ];

            $query = /** @lang text */
                "INSERT INTO temp(history_id, old_name, old_state, old_nbt, new_name, new_state, new_nbt) 
                 VALUES(:history_id, :old_name, :old_state, :old_nbt, :new_name, :new_state, :new_nbt)";

            $arg = [];
            DataConnectorHelper::asGenericStatement(
                $dialect,
                $query,
                $arg,
                "upgrade-blocks-query",
                $variables,
                $parameters
            );
            $queries[] = $query;
            $args = array_merge($args, $arg);
        }

        yield from DataConnectorHelper::asyncMultiInsertRaw($this->connector, $queries, $args);
    }

    private function upgradeInventoryTable(string $dialect): Generator
    {
        $offset = 0;
        /** @var array[] $rows */
        while (count($rows = yield from DataConnectorHelper::asyncSelectRaw($this->connector, /** @lang text */ "SELECT * FROM inventories_log LIMIT " . self::SELECT_LIMIT . " OFFSET $offset;")) > 0) {
            yield from $this->connector->asyncChange(QueriesConst::BEGIN_TRANSACTION);
            yield from $this->upgradeInventory($dialect, static fn() => $rows);
            yield from $this->connector->asyncChange(QueriesConst::END_TRANSACTION);

            $offset += self::SELECT_LIMIT;
        }
    }

    private function upgradeInventory(string $dialect, Closure $onPatch): Generator
    {
        /** @var array[] $rows */
        $rows = $onPatch();

        $deserializer = GlobalItemDataHandlers::getDeserializer();
        $upgrader = GlobalItemDataHandlers::getUpgrader();

        /** @var string[] $queries */
        $queries = [];
        $args = [];

        $variables = [
            "history_id" => new GenericVariable("history_id", GenericVariable::TYPE_INT, null),
            "slot" => new GenericVariable("slot", GenericVariable::TYPE_INT, null),
            "old_name" => new GenericVariable("old_name", GenericVariable::TYPE_STRING, null),
            "old_nbt" => new GenericVariable("old_nbt", "?" . GenericVariable::TYPE_STRING, null),
            "old_amount" => new GenericVariable("old_amount", GenericVariable::TYPE_INT, null),
            "new_name" => new GenericVariable("new_name", GenericVariable::TYPE_STRING, null),
            "new_nbt" => new GenericVariable("new_nbt", "?" . GenericVariable::TYPE_STRING, null),
            "new_amount" => new GenericVariable("new_amount", GenericVariable::TYPE_INT, null),
        ];

        foreach ($rows as $row) {
            $logId = (int)$row["history_id"];
            $slot = (int)$row["slot"];

            $oldId = (int)$row["old_id"];
            $oldMeta = (int)$row["old_meta"];
            $oldAmount = (int)$row["old_amount"];
            $oldNbt = (string)$row["old_nbt"];

            if ($oldId === 0 && $oldMeta === 0) {
                $oldItem = VanillaItems::AIR();
            } else {
                try {
                    $oldItemStack = $upgrader->upgradeItemTypeDataInt($oldId, $oldMeta, $oldAmount, Utils::deserializeNBT($oldNbt));
                } catch (SavedDataLoadingException) {
                    $this->getLogger()->debug("Could not upgrade old legacy item $oldId:$oldMeta");
                    yield from $this->connector->asyncChange(QueriesConst::PURGE_ID, ["log_id" => $logId]);
                    continue;
                }

                try {
                    $oldItem = $deserializer->deserializeStack($oldItemStack);
                } catch (ItemTypeSerializeException $e) {
                    $this->getLogger()->debug($e->getMessage());
                    yield from $this->connector->asyncChange(QueriesConst::PURGE_ID, ["log_id" => $logId]);
                    continue;
                }
            }

            $newId = (int)$row["new_id"];
            $newMeta = (int)$row["new_meta"];
            $newAmount = (int)$row["new_amount"];
            $newNbt = (string)$row["new_nbt"];

            if ($newId === 0 && $newMeta === 0) {
                $newItem = VanillaItems::AIR();
            } else {
                try {
                    $newItemStack = $upgrader->upgradeItemTypeDataInt($newId, $newMeta, $newAmount, Utils::deserializeNBT($newNbt));
                } catch (SavedDataLoadingException) {
                    $this->getLogger()->debug("Could not upgrade new legacy item $newId:$newMeta");
                    yield from $this->connector->asyncChange(QueriesConst::PURGE_ID, ["log_id" => $logId]);
                    continue;
                }

                try {
                    $newItem = $deserializer->deserializeStack($newItemStack);
                } catch (ItemTypeSerializeException $e) {
                    $this->getLogger()->debug($e->getMessage());
                    yield from $this->connector->asyncChange(QueriesConst::PURGE_ID, ["log_id" => $logId]);
                    continue;
                }
            }

            $parameters = [
                "history_id" => $logId,
                "slot" => $slot,
                "old_name" => $oldItem->getName(),
                "old_nbt" => $oldItem->isNull() ? null : Utils::serializeNBT($oldItem->nbtSerialize($slot)),
                "old_amount" => $oldAmount,
                "new_name" => $newItem->getName(),
                "new_nbt" => $newItem->isNull() ? null : Utils::serializeNBT($newItem->nbtSerialize($slot)),
                "new_amount" => $newAmount
            ];

            $query = /** @lang text */
                "INSERT INTO temp(history_id, slot, old_name, old_nbt, old_amount, new_name, new_nbt, new_amount)
                VALUES(:history_id, :slot, :old_name, :old_nbt, :old_amount, :new_name, :new_nbt, :new_amount);";

            $arg = [];
            DataConnectorHelper::asGenericStatement(
                $dialect,
                $query,
                $arg,
                "upgrade-inventory-query",
                $variables,
                $parameters
            );
            $queries[] = $query;
            $args = array_merge($args, $arg);
        }

        yield from DataConnectorHelper::asyncMultiInsertRaw($this->connector, $queries, $args);
    }
}