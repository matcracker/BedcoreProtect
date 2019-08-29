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

use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\serializable\SerializableItem;
use matcracker\BedcoreProtect\serializable\SerializableWorld;
use matcracker\BedcoreProtect\tasks\async\AsyncInventoriesQueryGenerator;
use matcracker\BedcoreProtect\tasks\async\AsyncLogsQueryGenerator;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;

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
            'slot' => $slot,
            'old_id' => $oldItem->getId(),
            'old_meta' => $oldItem->getDamage(),
            'old_nbt' => Utils::serializeNBT($oldItem->getNamedTag()),
            'old_amount' => $oldItem->getCount(),
            'new_id' => $newItem->getId(),
            'new_meta' => $newItem->getDamage(),
            'new_nbt' => Utils::serializeNBT($newItem->getNamedTag()),
            'new_amount' => $newItem->getCount()
        ]);

    }

    public function addInventoryLogByPlayer(Player $player, Inventory $inventory, Position $inventoryPosition): void
    {
        /**@var SerializableItem[] $contents */
        $contents = array_map(static function (Item $item): SerializableItem {
            return SerializableItem::toSerializableItem($item);
        }, $inventory->getContents());

        $positions = array_fill(0, count($contents), new SerializableWorld(
            $inventoryPosition->getFloorX(),
            $inventoryPosition->getFloorY(),
            $inventoryPosition->getFloorZ(),
            $inventoryPosition->getLevel()->getName()
        ));

        Await::f2c(function () use ($contents, $player, $positions) {
            $lastLogId = (int)(yield $this->getLastLogId())[0]['lastId'];
            $inventoriesTask = new AsyncInventoriesQueryGenerator($this->connector, $lastLogId, $contents);
            $logsTask = new AsyncLogsQueryGenerator($this->connector, Utils::getEntityUniqueId($player), $positions, Action::REMOVE(), $inventoriesTask);
            Server::getInstance()->getAsyncPool()->submitTask($logsTask);
        }, function () {
            //NOOP
        });
    }

}