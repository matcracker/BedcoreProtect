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
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\serializable\SerializableEntity;
use matcracker\BedcoreProtect\storage\QueryManager;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\Server;
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

    final protected function addRawEntity(string $uuid, string $name, string $classPath = '', string $address = '127.0.0.1'): Generator
    {
        $this->connector->executeInsert(QueriesConst::ADD_ENTITY, [
            'uuid' => $uuid,
            'name' => $name,
            'path' => $classPath,
            'address' => $address
        ], yield, yield Await::REJECT);

        return yield Await::ONCE;
    }

    public function addEntityLogByEntity(Entity $damager, Entity $entity, Action $action): void
    {
        $damager = SerializableEntity::serialize($damager);
        $entity = SerializableEntity::serialize($entity);

        Await::f2c(
            function () use ($damager, $entity, $action): Generator {
                yield $this->addEntityGenerator($damager);
                yield $this->addEntityGenerator($entity);

                /** @var int $lastId */
                $lastId = yield $this->addRawLog($damager->getUniqueId(), $entity, $action);
                yield $this->addEntityLog($lastId, $entity);
            }
        );
    }

    /**
     * @param SerializableEntity $entity
     * @return Generator
     * @internal
     */
    final public function addEntityGenerator(SerializableEntity $entity): Generator
    {
        return $this->addRawEntity($entity->getUniqueId(), $entity->getName(), $entity->getClassPath(), $entity->getAddress());
    }

    final protected function addEntityLog(int $logId, SerializableEntity $entity): Generator
    {
        return $this->executeInsert(QueriesConst::ADD_ENTITY_LOG, [
            'log_id' => $logId,
            'uuid' => $entity->getUniqueId(),
            'id' => $entity->getId(),
            'nbt' => $entity->getSerializedNbt()
        ]);
    }

    public function addEntityLogByBlock(Entity $entity, Block $block, Action $action): void
    {
        $entity = SerializableEntity::serialize($entity);
        $block = SerializableBlock::serialize($block);
        Await::f2c(
            function () use ($entity, $block, $action): Generator {
                yield $this->addEntityGenerator($entity);

                $name = $block->getName();
                /** @var int $lastId */
                $lastId = yield $this->addRawLog("{$name}-uuid", $block, $action);

                yield $this->addEntityLog($lastId, $entity);
            }
        );
    }

    final public function addEntity(Entity $entity): void
    {
        $entity = SerializableEntity::serialize($entity);
        Await::f2c(
            function () use ($entity): Generator {
                yield $this->addEntityGenerator($entity);
            }
        );
    }

    protected function onRollback(bool $rollback, Area $area, array $logIds, float $startTime, Closure $onComplete): Generator
    {
        $entityRows = [];

        if ($this->configParser->getRollbackEntities()) {
            $entityRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_ENTITIES, ['log_ids' => $logIds]);

            foreach ($entityRows as $row) {
                $action = Action::fromType((int)$row['action']);
                if (($rollback && $action->equals(Action::SPAWN())) || (!$rollback && !$action->equals(Action::SPAWN()))) {
                    $entityId = (int)$row['entityfrom_id'];
                    $entity = $area->getWorld()->getEntity($entityId);
                    if ($entity !== null) {
                        $entity->close();
                    }
                } else {
                    $logId = (int)$row['log_id'];
                    /** @var Entity $entityClass */
                    $entityClass = (string)$row['entity_classpath'];
                    $nbt = Utils::deserializeNBT($row['entityfrom_nbt']);
                    $entity = Entity::createEntity($entityClass::NETWORK_ID, $area->getWorld(), $nbt);
                    if ($entity !== null) {
                        yield $this->updateEntityId($logId, $entity);
                        $entity->spawnToAll();
                    }
                }
            }
        }

        if (($entities = count($entityRows)) > 0) {
            QueryManager::addReportMessage(microtime(true) - $startTime, 'rollback.entities', [$entities]);
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
