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
use matcracker\BedcoreProtect\tasks\async\AsyncInventoriesQueryGenerator;
use matcracker\BedcoreProtect\tasks\async\AsyncLogsQueryGenerator;
use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\Area;
use matcracker\BedcoreProtect\utils\PrimitiveBlock;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Chest;
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
        if ($inventory instanceof ContainerInventory) {
            $holder = $inventory->getHolder();
            $slot = $slotAction->getSlot();
            $sourceItem = $slotAction->getSourceItem();
            $targetItem = $slotAction->getTargetItem();

            $position = Position::fromObject($holder, $player->getLevel());

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
            "old_item_meta" => $oldItem->getDamage(),
            "old_item_nbt" => Utils::serializeNBT($oldItem->getNamedTag()),
            "old_item_amount" => $oldItem->getCount(),
            "new_item_id" => $newItem->getId(),
            "new_item_meta" => $newItem->getDamage(),
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
                $query .= "('{$logId}', '{$slot}', '{$item->getId()}', '{$item->getDamage()}', '{$nbt}', '{$item->getCount()}'),";
                $filledSlots++;
                $logId++;
            }
        }
        $query = mb_substr($query, 0, -1) . ";";
        /**@var PrimitiveBlock[] $positions */
        for ($i = 0; $i < $filledSlots; $i++) {
            $positions[$i] = new PrimitiveBlock(0, 0,
                $inventoryPosition->getFloorX(),
                $inventoryPosition->getFloorY(),
                $inventoryPosition->getFloorZ(),
                $inventoryPosition->getLevel()->getName()
            );
        }
        $inventoriesTask = new AsyncInventoriesQueryGenerator($query); //TODO: Hacked for the moment.
        $logsTask = new AsyncLogsQueryGenerator(Utils::getEntityUniqueId($player), $positions, Action::REMOVE(), $inventoriesTask);
        Server::getInstance()->getAsyncPool()->submitTask($logsTask);
        //$rawLogsQuery = $this->buildMultipleRawLogsQuery(Utils::getEntityUniqueId($player), $positions, Action::REMOVE());

        //$this->connector->executeInsertRaw($query);
    }

    /**
     * @param Area $area
     * @param CommandParser $parser
     * @return int
     * @internal
     */
    public function rollbackItems(Area $area, CommandParser $parser): int
    {
        return $this->executeInventoriesEdit(true, $area, $parser);
    }

    private function executeInventoriesEdit(bool $rollback, Area $area, CommandParser $parser): int
    {
        $query = $parser->buildInventoriesLogSelectionQuery($area->getBoundingBox(), !$rollback);
        $totalRows = 0;
        $world = $area->getWorld();
        $this->connector->executeSelectRaw($query, [],
            function (array $rows) use ($rollback, $world, &$totalRows) {
                if (count($rows) > 0) {
                    foreach ($rows as $row) {
                        $prefix = $rollback ? "old" : "new";
                        $amount = (int)$row["{$prefix}_item_amount"];
                        $nbt = Utils::deserializeNBT($row["{$prefix}_item_nbt"]);
                        $item = ItemFactory::get((int)$row["{$prefix}_item_id"], (int)$row["{$prefix}_item_meta"], $amount, $nbt);
                        $slot = (int)$row["slot"];
                        $vector = new Vector3((int)$row["x"], (int)$row["y"], (int)$row["z"]);
                        $tile = $world->getTile($vector);
                        if ($tile instanceof InventoryHolder) {
                            $inv = $tile instanceof Chest ? $tile->getRealInventory() : $tile->getInventory();
                            $inv->setItem($slot, $item);
                        }
                    }
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

    /**
     * @param Area $area
     * @param CommandParser $parser
     * @return int
     * @internal
     */
    public function restoreItems(Area $area, CommandParser $parser): int
    {
        return $this->executeInventoriesEdit(false, $area, $parser);
    }
}