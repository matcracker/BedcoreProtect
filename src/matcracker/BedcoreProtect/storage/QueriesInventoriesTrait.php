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

use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use poggit\libasynql\SqlError;

trait QueriesInventoriesTrait
{
    public function addLogInventoryByPlayer(Player $player, SlotChangeAction $slotAction): void
    {
        $this->addEntity($player);

        /**@var ContainerInventory $inventory */
        $inventory = $slotAction->getInventory();
        $holder = $inventory->getHolder();
        $slot = $slotAction->getSlot();
        $sourceItem = $slotAction->getSourceItem();
        $targetItem = $slotAction->getTargetItem();

        $position = Position::fromObject($holder, $player->getLevel());

        if ($sourceItem->getId() === $targetItem->getId()) { //TODO: CHECK POSITION OF DOUBLE CHEST
            $sourceCount = $sourceItem->getCount();
            $targetCount = $targetItem->getCount(); //Final count
            if ($targetCount > $sourceCount) {
                $diffAmount = $targetCount - $sourceCount; //Effective block added/removed
                $this->addRawLog(Utils::getEntityUniqueId($player), $position, QueriesConst::ADDED);
                $targetItem->setCount($diffAmount);
            } else {
                $this->addRawLog(Utils::getEntityUniqueId($player), $position, QueriesConst::REMOVED);
                $this->addLogInventory($inventory, $slot, $sourceItem, $targetItem);
                $this->addRawLog(Utils::getEntityUniqueId($player), $position, QueriesConst::ADDED);
            }
        } else if ($sourceItem->getId() !== ItemIds::AIR && $targetItem->getId() !== ItemIds::AIR) {
            $this->addRawLog(Utils::getEntityUniqueId($player), $position, QueriesConst::REMOVED);
            $this->addLogInventory($inventory, $slot, $sourceItem, $targetItem);
            $this->addRawLog(Utils::getEntityUniqueId($player), $position, QueriesConst::ADDED);
        } else if ($sourceItem->getId() !== ItemIds::AIR) {
            $this->addRawLog(Utils::getEntityUniqueId($player), $position, QueriesConst::REMOVED);
        } else {
            $this->addRawLog(Utils::getEntityUniqueId($player), $position, QueriesConst::ADDED);
        }
        $this->addLogInventory($inventory, $slot, $sourceItem, $targetItem);
    }

    private function addLogInventory(Inventory $inventory, int $slot, Item $oldItem, Item $newItem): void
    {
        $this->connector->executeInsert(QueriesConst::ADD_INVENTORY_LOG, [
            "inventory_name" => $inventory->getName(),
            "slot" => $slot,
            "old_item_id" => $oldItem->getId(),
            "old_item_damage" => $oldItem->getDamage(),
            "old_amount" => $oldItem->getCount(),
            "new_item_id" => $newItem->getId(),
            "new_item_damage" => $newItem->getDamage(),
            "new_amount" => $newItem->getCount()
        ]);
    }

    public function rollbackItems(Position $position, CommandParser $parser, ?callable $onSuccess = null, ?callable $onError = null): void
    {
        $this->executeInvetoriesEdit(true, $position, $parser, $onSuccess, $onError);
    }

    private function executeInvetoriesEdit(bool $rollback, Position $position, CommandParser $parser, ?callable $onSuccess = null, ?callable $onError = null): void
    {
        $query = $parser->buildInventoriesLogSelectionQuery($position, !$rollback);
        $this->connector->executeSelectRaw($query, [],
            function (array $rows) use ($rollback, $position, $onSuccess, $parser) {
                if (count($rows) > 0) {
                    $level = $position->getLevel();
                    $query = /**@lang text */
                        "UPDATE log_history SET \"rollback\" = '{$rollback}' WHERE ";

                    foreach ($rows as $row) {
                        $logId = (int)$row["log_id"];
                        $prefix = $rollback ? "old" : "new";
                        $amount = (int)$row["{$prefix}_amount"];
                        $item = ItemFactory::get((int)$row["{$prefix}_item_id"], (int)$row["{$prefix}_item_damage"], $amount);
                        $slot = (int)$row["slot"];
                        $vector = new Vector3((int)$row["x"], (int)$row["y"], (int)$row["z"]);
                        $tile = $level->getTile($vector);
                        if ($tile instanceof InventoryHolder) {
                            $inv = $tile->getInventory();
                            $inv->setItem($slot, $item);
                        }

                        $query .= "log_id = '$logId' OR ";
                    }
                    $query = rtrim($query, " OR ") . ";";
                    $this->connector->executeInsertRaw($query);
                }

                if ($onSuccess !== null) {
                    $onSuccess(count($rows), $parser);
                }

            },
            function (SqlError $error) use ($onError) {
                if ($onError !== null) {
                    $onError($error);
                }
            }
        );
    }

    public function restoreItems(Position $position, CommandParser $parser, ?callable $onSuccess = null, ?callable $onError = null): void
    {
        $this->executeInvetoriesEdit(false, $position, $parser, $onSuccess, $onError);
    }
}