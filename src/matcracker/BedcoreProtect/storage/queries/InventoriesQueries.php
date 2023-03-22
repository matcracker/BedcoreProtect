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

use Generator;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\command\CommandSender;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\World\Position;
use pocketmine\World\World;
use SOFe\AwaitGenerator\Await;
use function microtime;

/**
 * It contains all the queries methods related to inventories.
 *
 * Class InventoriesQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class InventoriesQueries extends Query
{
    public function addInventorySlotLogByPlayer(Player $player, SlotChangeAction $slotAction): void
    {
        $inventory = $slotAction->getInventory();

        if (!$inventory instanceof BlockInventory) {
            return;
        }

        /**
         * Note for double chest holder
         * It always logs the position of the left chest.
         * @see DoubleChestInventory::getHolder()
         */
        $holder = $inventory->getHolder();
        if (!($holder instanceof Vector3)) {
            return;
        }

        $playerUuid = EntityUtils::getUniqueId($player);
        $worldName = $player->getWorld()->getFolderName();
        $time = microtime(true);

        Await::f2c(
            function () use ($playerUuid, $slotAction, $holder, $worldName, $time): Generator {
                $slot = $slotAction->getSlot();
                $sourceItem = $slotAction->getSourceItem();
                $targetItem = $slotAction->getTargetItem();

                if ($sourceItem->equals($targetItem)) {
                    $sourceCount = $sourceItem->getCount();
                    $targetCount = $targetItem->getCount(); //Final count
                    if ($targetCount > $sourceCount) {
                        $action = Action::ADD();
                        $targetItem->setCount($targetCount - $sourceCount); //Effective number of blocks added
                    } else {
                        $action = Action::REMOVE();
                        $sourceItem->setCount($sourceCount - $targetCount); //Effective number of blocks removed
                    }
                } elseif (!$sourceItem->isNull() && !$targetItem->isNull()) {
                    yield from $this->addInventorySlotLog($playerUuid, $slot, $sourceItem, $targetItem, $holder, $worldName, Action::REMOVE(), $time);
                    $action = Action::ADD();
                } elseif (!$sourceItem->isNull()) {
                    $action = Action::REMOVE();
                } else {
                    $action = Action::ADD();
                }

                yield from $this->addInventorySlotLog($playerUuid, $slot, $sourceItem, $targetItem, $holder, $worldName, $action, $time);
            }
        );
    }

    final protected function addInventorySlotLog(string $uuid, int $slot, Item $oldItem, Item $newItem, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        /** @var int $lastId */
        [$lastId] = yield from $this->addRawLog($uuid, $position, $worldName, $action, $time);

        return yield from $this->connector->asyncInsert(QueriesConst::ADD_INVENTORY_LOG, [
            "log_id" => $lastId,
            "slot" => $slot,
            "old_name" => $oldItem->getName(),
            "old_nbt" => $oldItem->isNull() ? null : Utils::serializeNBT($oldItem->nbtSerialize($slot)),
            "old_amount" => $oldItem->getCount(),
            "new_name" => $newItem->getName(),
            "new_nbt" => $newItem->isNull() ? null : Utils::serializeNBT($newItem->nbtSerialize($slot)),
            "new_amount" => $newItem->getCount()
        ]);
    }

    /**
     * Item frames do not have an inventory but we treat them as if they did.
     *
     * @param Player $player
     * @param Item $item
     * @param Action $action
     * @param Vector3 $position
     * @param string $worldName
     */
    public function addItemFrameSlotLog(Player $player, Item $item, Action $action, Vector3 $position, string $worldName): void
    {
        Await::g2c($this->addInventorySlotLog(EntityUtils::getUniqueId($player), 0, $item, $item, $position, $worldName, $action, microtime(true)));
    }

    public function addInventoryLogByPlayer(Player $player, Inventory $inventory, Position $inventoryPosition): void
    {
        $worldName = $player->getWorld()->getFolderName();
        $time = microtime(true);

        $contents = $inventory->getContents();

        $this->getMutex()->putClosure(
            function () use ($player, $worldName, $time, $contents, $inventoryPosition): Generator {
                yield from $this->connector->asyncGeneric(QueriesConst::BEGIN_TRANSACTION);

                /**
                 * @var int $slot
                 * @var Item $content
                 */
                foreach ($contents as $slot => $content) {
                    yield from $this->addInventorySlotLog(
                        EntityUtils::getUniqueId($player),
                        $slot,
                        $content,
                        VanillaItems::AIR(),
                        $inventoryPosition,
                        $worldName,
                        Action::REMOVE(),
                        $time
                    );
                }

                yield from $this->connector->asyncGeneric(QueriesConst::END_TRANSACTION);
            }
        );
    }

    public function onRollback(CommandSender $sender, World $world, bool $rollback, array $logIds): Generator
    {
        $cntItems = 0;

        if ($this->plugin->getParsedConfig()->getRollbackItems()) {
            if ($rollback) {
                /** @var array $rows */
                $rows = yield from $this->connector->asyncSelect(QueriesConst::GET_ROLLBACK_OLD_INVENTORIES, ["log_ids" => $logIds]);
                $prefix = "old";
            } else {
                /** @var array $rows */
                $rows = yield from $this->connector->asyncSelect(QueriesConst::GET_ROLLBACK_NEW_INVENTORIES, ["log_ids" => $logIds]);
                $prefix = "new";
            }

            foreach ($rows as $row) {
                $tile = $world->getTileAt((int)$row["x"], (int)$row["y"], (int)$row["z"]);
                if ($tile instanceof InventoryHolder) {
                    /** @var string|null $serializedNbt */
                    $serializedNbt = $row["{$prefix}_nbt"];
                    if ($serializedNbt !== null) {
                        $item = Item::nbtDeserialize(Utils::deserializeNBT((string)$serializedNbt));
                    } else {
                        $item = VanillaItems::AIR();
                    }

                    $tile->getInventory()->setItem((int)$row["slot"], $item);
                    $cntItems += $item->getCount();
                }
            }
        }

        //On success
        $onSuccess = yield Await::RESOLVE;
        $onSuccess($cntItems);
        yield Await::REJECT;

        return yield Await::ONCE;
    }
}
