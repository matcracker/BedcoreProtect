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
use matcracker\BedcoreProtect\enums\ActionType;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\Server;
use pocketmine\World\World;
use SOFe\AwaitGenerator\Await;
use function count;
use function mb_strtolower;
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
        static $map = [
            "flow-uuid" => "#Flow",
            "water-uuid" => "#Water",
            "still water-uuid" => "#Water",
            "lava-uuid" => "#Lava",
            "still lava-uuid" => "#Lava",
            "fire block-uuid" => "#Fire",
            "leaves-uuid" => "#Decay"
        ];

        yield from $this->addRawEntity(Server::getInstance()->getServerUniqueId()->toString(), "#console");

        $queries = [];
        foreach ($map as $uuid => $name) {
            $queries[] = $this->addRawEntity($uuid, $name);
        }

        yield from Await::all($queries);
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

    final protected function addEntityLog(int $logId, Entity $entity, ?string $serializedNbt): Generator
    {
        return yield from $this->connector->asyncInsert(QueriesConst::ADD_ENTITY_LOG, [
            "log_id" => $logId,
            "uuid" => EntityUtils::getUniqueId($entity),
            "id" => $entity->getId(),
            "nbt" => $serializedNbt
        ]);
    }

    public function addEntityLogByBlock(Entity $entity, Block $block, Action $action): void
    {
        $serializedNbt = EntityUtils::getSerializedNbt($entity);
        $worldName = $block->getPosition()->getWorld()->getFolderName();
        $time = microtime(true);

        Await::f2c(
            function () use ($entity, $serializedNbt, $block, $worldName, $action, $time): Generator {
                yield from $this->addEntity($entity);

                $blockName = $block->getName();
                $uuid = mb_strtolower("$blockName-uuid");
                yield from $this->addRawEntity($uuid, "#$blockName");

                /** @var int $lastId */
                [$lastId] = yield from $this->addRawLog($uuid, $block->getPosition(), $worldName, $action, $time);

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
                $action = ActionType::fromId((int)$row["action"]);
                if (
                    ($rollback && ($action === ActionType::KILL() || $action === ActionType::DESPAWN()) ||
                    (!$rollback && $action === ActionType::SPAWN()))
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
