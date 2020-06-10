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

use Closure;
use Generator;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\storage\QueryManager;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;
use function count;
use function get_class;
use function microtime;

/**
 * It contains all the queries methods related to entities.
 *
 * Class EntitiesQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class EntitiesQueries extends Query
{
    public function addDefaultEntities(): void
    {
        Await::f2c(
            function (): Generator {
                $serverUuid = Server::getInstance()->getServerUniqueId()->toString();
                yield $this->addRawEntity($serverUuid, '#console');
                yield $this->addRawEntity('flow-uuid', '#flow');
                yield $this->addRawEntity('water-uuid', '#water');
                yield $this->addRawEntity('still water-uuid', '#water');
                yield $this->addRawEntity('lava-uuid', '#lava');
                yield $this->addRawEntity('still lava-uuid', '#lava');
                yield $this->addRawEntity('fire block-uuid', '#fire');
                yield $this->addRawEntity('leaves-uuid', '#decay');
            }
        );
    }

    /**
     * @param string $uuid
     * @param string $name
     * @param string $classPath
     * @return Generator
     * @internal
     */
    final public function addRawEntity(string $uuid, string $name, string $classPath = ''): Generator
    {
        $this->connector->executeInsert(QueriesConst::ADD_ENTITY, [
            'uuid' => $uuid,
            'name' => $name,
            'path' => $classPath
        ], yield, yield Await::REJECT);

        return yield Await::ONCE;
    }

    public function addEntityLogByEntity(Entity $damager, Entity $entity, Action $action): void
    {
        $entityNbt = EntityUtils::getSerializedNbt($entity);
        $worldName = $entity->getLevelNonNull()->getName();
        $time = microtime(true);
        Await::f2c(
            function () use ($damager, $entity, $entityNbt, $worldName, $action, $time): Generator {
                yield $this->addEntityGenerator($damager);
                yield $this->addEntityGenerator($entity);

                /** @var int $lastId */
                $lastId = yield $this->addRawLog(EntityUtils::getUniqueId($damager), $entity->asVector3(), $worldName, $action, $time);
                yield $this->addEntityLog($lastId, $entity, $entityNbt);
            }
        );
    }

    /**
     * @param Entity $entity
     * @return Generator
     * @internal
     */
    final public function addEntityGenerator(Entity $entity): Generator
    {
        return $this->addRawEntity(
            EntityUtils::getUniqueId($entity),
            EntityUtils::getName($entity),
            get_class($entity)
        );
    }

    final protected function addEntityLog(int $logId, Entity $entity, ?string $serializedNbt): Generator
    {
        return $this->executeInsert(QueriesConst::ADD_ENTITY_LOG, [
            'log_id' => $logId,
            'uuid' => EntityUtils::getUniqueId($entity),
            'id' => $entity->getId(),
            'nbt' => $serializedNbt
        ]);
    }

    public function addEntityLogByBlock(Entity $entity, Block $block, Action $action): void
    {
        $serializedNbt = EntityUtils::getSerializedNbt($entity);
        $worldName = $block->getLevelNonNull()->getName();
        $time = microtime(true);

        Await::f2c(
            function () use ($entity, $serializedNbt, $block, $worldName, $action, $time): Generator {
                yield $this->addEntityGenerator($entity);

                $name = $block->getName();
                /** @var int $lastId */
                $lastId = yield $this->addRawLog("{$name}-uuid", $block->asVector3(), $worldName, $action, $time);

                yield $this->addEntityLog($lastId, $entity, $serializedNbt);
            }
        );
    }

    final public function addEntity(Entity $entity): void
    {
        Await::f2c(
            function () use ($entity): Generator {
                yield $this->addEntityGenerator($entity);
            }
        );
    }

    protected function onRollback(bool $rollback, Area $area, CommandParser $commandParser, array $logIds, Closure $onComplete): Generator
    {
        $entityRows = [];

        if ($this->configParser->getRollbackEntities()) {
            $world = $area->getWorld();

            $entityRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_ENTITIES, ['log_ids' => $logIds]);

            foreach ($entityRows as $row) {
                $action = Action::fromType((int)$row['action']);
                if (
                    ($rollback && ($action->equals(Action::KILL()) || $action->equals(Action::DESPAWN()))) ||
                    (!$rollback && $action->equals(Action::SPAWN()))
                ) {
                    /** @var Entity $entityClass */
                    $entityClass = (string)$row['entity_classpath'];
                    $entity = Entity::createEntity($entityClass::NETWORK_ID, $world, Utils::deserializeNBT($row['entityfrom_nbt']));
                    if ($entity !== null) {
                        yield $this->updateEntityId((int)$row['log_id'], $entity);
                        $entity->spawnToAll();
                    }
                } else {
                    $entity = $world->getEntity((int)$row['entityfrom_id']);
                    if ($entity !== null) {
                        $entity->close();
                    }
                }
            }
        }

        if (($entities = count($entityRows)) > 0) {
            QueryManager::addReportMessage($commandParser->getSenderName(), 'rollback.entities', [$entities]);
        }

        $onComplete();
    }

    final protected function updateEntityId(int $logId, Entity $entity): Generator
    {
        $this->connector->executeInsert(QueriesConst::UPDATE_ENTITY_ID, [
            'log_id' => $logId,
            'entity_id' => $entity->getId()
        ], yield, yield Await::REJECT);

        return yield Await::ONCE;
    }
}
