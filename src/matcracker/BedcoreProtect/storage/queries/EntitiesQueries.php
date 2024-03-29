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

namespace matcracker\BedcoreProtect\storage\queries;

use Generator;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\World\World;
use SOFe\AwaitGenerator\Await;
use function count;
use function microtime;

/**
 * It contains all the queries methods related to entities.
 *
 * Class EntitiesQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class EntitiesQueries extends Query
{
    public function addDefaultEntities(): Generator
    {
        yield from $this->addRawEntity(Server::getInstance()->getServerUniqueId()->toString(), "#Console");
    }

    /**
     * @param string $uuid
     * @param string $name
     * @return Generator
     */
    final protected function addRawEntity(string $uuid, string $name): Generator
    {
        return yield from $this->connector->asyncInsert(QueriesConst::ADD_ENTITY, [
            "uuid" => $uuid,
            "name" => $name
        ]);
    }

    public function addEntityLogByEntity(Entity $damager, Entity $entity, Action $action): void
    {
        $entityNbt = EntityUtils::getSerializedNbt($entity);
        $worldName = $entity->getWorld()->getFolderName();
        $time = microtime(true);
        Await::f2c(
            function () use ($damager, $entity, $entityNbt, $worldName, $action, $time): Generator {
                yield from Await::all([
                    $this->addEntity($damager),
                    $this->addEntity($entity)
                ]);

                /** @var int $lastId */
                [$lastId] = yield from $this->addRawLog(EntityUtils::getUniqueId($damager), $entity->getPosition(), $worldName, $action, $time);
                yield from $this->addEntityLog($lastId, $entity, $entityNbt);
            }
        );
    }

    /**
     * @param Entity $entity
     * @return Generator
     * @internal
     */
    final public function addEntity(Entity $entity): Generator
    {
        return yield from $this->addRawEntity(
            EntityUtils::getUniqueId($entity),
            EntityUtils::getName($entity)
        );
    }

    /**
     * @param Block $block
     * @return Generator
     * @internal
     */
    final public function addBlock(Block $block): Generator
    {
        return yield from $this->addRawEntity(
            BlockUtils::getUniqueId($block),
            BlockUtils::getAliasName($block)
        );
    }

    final protected function addEntityLog(int $logId, Entity $entity, ?string $serializedNbt): Generator
    {
        return yield from $this->connector->asyncInsert(QueriesConst::ADD_ENTITY_LOG, [
            "log_id" => $logId,
            "uuid" => EntityUtils::getUniqueId($entity),
            "id" => $entity->getId(),
            "nbt" => $serializedNbt
        ]);
    }

    public function addEntityLogByBlock(Entity $entity, Block $block, Action $action, ?Position $position = null, bool $trackBlockUuid = true): void
    {
        $serializedNbt = EntityUtils::getSerializedNbt($entity);
        $position ??= $block->getPosition();
        $worldName = $position->getWorld()->getFolderName();
        $time = microtime(true);

        Await::f2c(
            function () use ($entity, $position, $serializedNbt, $block, $worldName, $action, $time, $trackBlockUuid): Generator {
                yield from $this->addEntity($entity);

                $uuid = yield from $this->getUuidByPosition($position, $worldName);
                if ($uuid === null) {
                    if ($trackBlockUuid) {
                        $uuid = BlockUtils::getUniqueId($block);
                        yield from $this->addBlock($block);
                    } else {
                        return 0 && yield;
                    }
                }

                /** @var int $lastId */
                [$lastId] = yield from $this->addRawLog($uuid, $position, $worldName, $action, $time);

                yield from $this->addEntityLog($lastId, $entity, $serializedNbt);
            }
        );
    }

    public function onRollback(CommandSender $sender, World $world, bool $rollback, array $logIds): Generator
    {
        if ($this->plugin->getParsedConfig()->getRollbackEntities()) {
            $rows = yield from $this->connector->asyncSelect(QueriesConst::GET_ROLLBACK_ENTITIES, ["log_ids" => $logIds]);

            $factory = EntityFactory::getInstance();

            foreach ($rows as $row) {
                $action = Action::from((int)$row["action"]);
                if (
                    ($rollback && ($action === Action::KILL || $action === Action::DESPAWN)) ||
                    (!$rollback && $action === Action::SPAWN)
                ) {
                    $entity = $factory->createFromData($world, Utils::deserializeNBT($row["entityfrom_nbt"]));
                    if ($entity !== null) {
                        yield from $this->updateEntityId((int)$row["log_id"], $entity);
                        $entity->spawnToAll();
                    }
                } else {
                    $world->getEntity((int)$row["entityfrom_id"])?->flagForDespawn();
                }
            }
        } else {
            $rows = [];
        }

        return yield from Await::promise(static fn($resolve, $reject) => $resolve(count($rows)));
    }

    final protected function updateEntityId(int $logId, Entity $entity): Generator
    {
        return yield from $this->connector->asyncInsert(QueriesConst::UPDATE_ENTITY_ID, [
            "log_id" => $logId,
            "entity_id" => $entity->getId()
        ]);
    }
}
