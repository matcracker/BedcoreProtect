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

namespace matcracker\BedcoreProtect\storage\queries;

use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\tile\Container;
use pocketmine\inventory\BlockInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use poggit\libasynql\SqlError;

/**
 * It contains all the queries methods related to inventories.
 *
 * Trait QueriesInventoriesTrait
 * @package matcracker\BedcoreProtect\storage\queries
 */
trait QueriesInventoriesTrait
{
    public function addInventorySlotLogByPlayer(Player $player, SlotChangeAction $slotAction): void
    {
        $inventory = $slotAction->getInventory();
        if ($inventory instanceof BlockInventory) {
            $holder = $inventory->getHolder();
            $slot = $slotAction->getSlot();
            $sourceItem = $slotAction->getSourceItem();
            $targetItem = $slotAction->getTargetItem();

            $position = Position::fromObject($holder, $player->getWorld());

            if ($sourceItem->getId() === $targetItem->getId()) {
                $sourceCount = $sourceItem->getCount();
                $targetCount = $targetItem->getCount(); //Final count
                if ($targetCount > $sourceCount) {
                    $diffAmount = $targetCount - $sourceCount; //Effective number of blocks added/removed
                    $this->addRawLog(Utils::getEntityUniqueId($player), $position, Action::ADD());
                    $targetItem->setCount($diffAmount);
                } else {
                    $this->addRawLog(Utils::getEntityUniqueId($player), $position, Action::REMOVE());
                    $this->addInventorySlotLog($slot, $sourceItem, $targetItem);
                    $this->addRawLog(Utils::getEntityUniqueId($player), $position, Action::ADD());
                }
            } else if (!$sourceItem->isNull() && !$targetItem->isNull()) {
                $this->addRawLog(Utils::getEntityUniqueId($player), $position, Action::REMOVE());
                $this->addInventorySlotLog($slot, $sourceItem, $targetItem);
                $this->addRawLog(Utils::getEntityUniqueId($player), $position, Action::ADD());
            } else if (!$sourceItem->isNull()) {
                $this->addRawLog(Utils::getEntityUniqueId($player), $position, Action::REMOVE());
            } else {
                $this->addRawLog(Utils::getEntityUniqueId($player), $position, Action::ADD());
            }
            $this->addInventorySlotLog($slot, $sourceItem, $targetItem);
        }
    }

    protected final function addInventorySlotLog(int $slot, Item $oldItem, Item $newItem): void
    {
        $this->connector->executeInsert(QueriesConst::ADD_INVENTORY_LOG, [
            "slot" => $slot,
            "old_item_id" => $oldItem->getId(),
            "old_item_meta" => $oldItem->getMeta(),
            "old_item_nbt" => Utils::serializeNBT($oldItem->getNamedTag()),
            "old_item_amount" => $oldItem->getCount(),
            "new_item_id" => $newItem->getId(),
            "new_item_meta" => $newItem->getMeta(),
            "new_item_nbt" => Utils::serializeNBT($newItem->getNamedTag()),
            "new_item_amount" => $newItem->getCount()
        ]);

    }

    public function addInventoryLogByPlayer(Player $player, Inventory $inventory, Position $inventoryPosition): void
    {
        $size = $inventory->getSize();
        $logId = $this->getLastLogId() + 1;

        $query = /**@lang text */
            "INSERT INTO inventories_log(history_id, slot, old_item_id, old_item_meta, old_item_nbt, old_item_amount) VALUES";

        $filledSlots = 0;
        for ($slot = 0; $slot < $size; $slot++) {
            $item = $inventory->getItem($slot);
            if (!$item->isNull()) {
                $nbt = Utils::serializeNBT($item->getNamedTag());
                $query .= "('{$logId}', '{$slot}', '{$item->getId()}', '{$item->getMeta()}', '{$nbt}', '{$item->getCount()}'),";
                $filledSlots++;
                $logId++;
            }
        }
        $query = mb_substr($query, 0, -1) . ";";
        /**@var Position[] $positions */
        $positions = array_fill(0, $filledSlots, $inventoryPosition);
        $rawLogsQuery = $this->buildMultipleRawLogsQuery(Utils::getEntityUniqueId($player), $positions, Action::REMOVE());

        $this->connector->executeInsertRaw($rawLogsQuery);
        $this->connector->executeInsertRaw($query);
    }

    protected function rollbackItems(Position $position, CommandParser $parser): int
    {
        return $this->executeInventoriesEdit(true, $position, $parser);
    }

    private function executeInventoriesEdit(bool $rollback, Position $position, CommandParser $parser): int
    {
        $query = $parser->buildInventoriesLogSelectionQuery($position, !$rollback);
        $totalRows = 0;
        $world = $position->getWorld();
        $this->connector->executeSelectRaw($query, [],
            function (array $rows) use ($rollback, $world, &$totalRows) {
                if (count($rows) > 0) {
                    $query = /**@lang text */
                        "UPDATE log_history SET rollback = '{$rollback}' WHERE ";

                    foreach ($rows as $row) {
                        $logId = (int)$row["log_id"];
                        $prefix = $rollback ? "old" : "new";
                        $amount = (int)$row["{$prefix}_amount"];
                        $nbt = Utils::deserializeNBT($row["{$prefix}_item_nbt"]);
                        $item = ItemFactory::get((int)$row["{$prefix}_item_id"], (int)$row["{$prefix}_item_meta"], $amount, $nbt);
                        $slot = (int)$row["slot"];
                        $vector = new Vector3((int)$row["x"], (int)$row["y"], (int)$row["z"]);
                        $tile = $world->getTile($vector);
                        if ($tile instanceof Container) {
                            $inv = $tile->getRealInventory();
                            $inv->setItem($slot, $item);
                        }

                        $query .= "log_id = '$logId' OR ";
                    }
                    $query = mb_substr($query, 0, -4) . ";";
                    $this->connector->executeInsertRaw($query);
                }

                $totalRows = count($rows);

            },
            static function (SqlError $error) {
                throw $error;
            }
        );

        $this->connector->waitAll();

        return $totalRows;
    }

    protected function restoreItems(Position $position, CommandParser $parser): int
    {
        return $this->executeInventoriesEdit(false, $position, $parser);
    }
}