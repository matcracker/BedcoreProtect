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

use Closure;
use Generator;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\QueryManager;
use matcracker\BedcoreProtect\utils\AwaitMutex;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\tile\Chest;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function count;
use function microtime;

/**
 * It contains all the queries methods related to inventories.
 *
 * Class InventoriesQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class InventoriesQueries extends Query
{
    private AwaitMutex $mutexInventory;

    public function __construct(Main $plugin, DataConnector $connector)
    {
        parent::__construct($plugin, $connector);
        $this->mutexInventory = new AwaitMutex();
    }

    public function addInventorySlotLogByPlayer(Player $player, SlotChangeAction $slotAction): void
    {
        $inventory = $slotAction->getInventory();

        if (!$inventory instanceof ContainerInventory) {
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
        $worldName = $player->getLevelNonNull()->getFolderName();
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
                    yield $this->addInventorySlotLog($playerUuid, $slot, $sourceItem, $targetItem, $holder, $worldName, Action::REMOVE(), $time);
                    $action = Action::ADD();
                } elseif (!$sourceItem->isNull()) {
                    $action = Action::REMOVE();
                } else {
                    $action = Action::ADD();
                }

                yield $this->addInventorySlotLog($playerUuid, $slot, $sourceItem, $targetItem, $holder, $worldName, $action, $time);
            }
        );
    }

    final protected function addInventorySlotLog(string $uuid, int $slot, Item $oldItem, Item $newItem, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        /** @var int $lastId */
        $lastId = yield $this->addRawLog($uuid, $position, $worldName, $action, $time);

        $this->connector->executeInsert(QueriesConst::ADD_INVENTORY_LOG, [
            "log_id" => $lastId,
            "slot" => $slot,
            "old_id" => $oldItem->getId(),
            "old_meta" => $oldItem->getDamage(),
            "old_nbt" => Utils::serializeNBT($oldItem->getNamedTag()),
            "old_amount" => $oldItem->getCount(),
            "new_id" => $newItem->getId(),
            "new_meta" => $newItem->getDamage(),
            "new_nbt" => Utils::serializeNBT($newItem->getNamedTag()),
            "new_amount" => $newItem->getCount()
        ], yield, yield Await::REJECT);

        return yield Await::ONCE;
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

    public function addInventoryLogByPlayer(Player $player, ContainerInventory $inventory, Position $inventoryPosition): void
    {
        $worldName = $player->getLevelNonNull()->getFolderName();
        $time = microtime(true);

        $contents = $inventory->getContents();

        $airItem = ItemFactory::get(ItemIds::AIR);

        $this->mutexInventory->putClosure(
            function () use ($player, $worldName, $time, $contents, $inventoryPosition, $airItem): Generator {
                yield $this->executeGeneric(QueriesConst::BEGIN_TRANSACTION);

                /**
                 * @var int $slot
                 * @var Item $content
                 */
                foreach ($contents as $slot => $content) {
                    yield $this->addInventorySlotLog(
                        EntityUtils::getUniqueId($player),
                        $slot,
                        $content,
                        $airItem,
                        $inventoryPosition,
                        $worldName,
                        Action::REMOVE(),
                        $time
                    );
                }

                yield $this->executeGeneric(QueriesConst::END_TRANSACTION);
            }
        );
    }

    protected function onRollback(bool $rollback, Level $world, CommandParser $commandParser, array $logIds, Closure $onComplete): Generator
    {
        $inventoryRows = [];

        if ($this->configParser->getRollbackItems()) {
            if ($rollback) {
                $inventoryRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_OLD_INVENTORIES, ["log_ids" => $logIds]);
                $prefix = "old";
            } else {
                $inventoryRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_NEW_INVENTORIES, ["log_ids" => $logIds]);
                $prefix = "new";
            }

            foreach ($inventoryRows as $row) {
                $amount = (int)$row["{$prefix}_amount"];
                /** @var CompoundTag|null $nbt */
                if (($nbt = $row["{$prefix}_nbt"]) !== null) {
                    $nbt = Utils::deserializeNBT($row["{$prefix}_nbt"]);
                }
                $item = ItemFactory::get((int)$row["{$prefix}_id"], (int)$row["{$prefix}_meta"], $amount, $nbt);
                $tile = $world->getTile(new Vector3((int)$row["x"], (int)$row["y"], (int)$row["z"]));

                if ($tile instanceof InventoryHolder) {
                    $inv = ($tile instanceof Chest) ? $tile->getRealInventory() : $tile->getInventory();
                    $inv->setItem((int)$row["slot"], $item);
                }
            }
        }

        if (($items = count($inventoryRows)) > 0) {
            QueryManager::addReportMessage(
                $commandParser->getSenderName(),
                $this->plugin->getLanguage()->translateString("rollback.items", [$items])
            );
        }

        $onComplete();
    }
}
