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
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\Player;
use SOFe\AwaitGenerator\Await;

/**
 * It contains all the queries methods related to entities.
 *
 * Trait QueriesEntitiesTrait
 * @package matcracker\BedcoreProtect\storage\queries
 */
trait QueriesEntitiesTrait
{

    public function addLogEntityByEntity(Entity $damager, Entity $entity, Action $action): void
    {
        $this->addEntity($damager);
        $this->addEntity($entity);

        $this->addRawLog(Utils::getEntityUniqueId($damager), $entity, $action);
        $entity->saveNBT();
        $entity->namedtag->setFloat("Health", $entity->getMaxHealth());

        $this->connector->executeInsert(QueriesConst::ADD_ENTITY_LOG, [
            'uuid' => Utils::getEntityUniqueId($entity),
            'id' => $entity->getId(),
            'nbt' => Utils::serializeNBT($entity->namedtag)
        ]);
    }

    public function addEntity(Entity $entity): void
    {
        $this->addRawEntity(Utils::getEntityUniqueId($entity), Utils::getEntityName($entity), get_class($entity), ($entity instanceof Player) ? $entity->getAddress() : "127.0.0.1");
    }

    protected final function addRawEntity(string $uuid, string $name, string $classPath = '', string $address = '127.0.0.1'): void
    {
        $this->connector->executeInsert(QueriesConst::ADD_ENTITY, [
            'uuid' => $uuid,
            'name' => $name,
            'path' => $classPath,
            'address' => $address
        ]);
    }

    public function rollbackEntities(bool $rollback, Area $area, CommandParser $commandParser, array $logIds): void
    {
        if ($this->configParser->getRollbackEntities()) {
            Await::f2c(function () use ($rollback, $area, $commandParser, $logIds) {
                $entityRows = yield $this->connector->executeSelect(QueriesConst::GET_ROLLBACK_ENTITIES, ['log_ids' => $logIds], yield, yield Await::REJECT) => Await::ONCE;
                foreach ($entityRows as $row) {
                    $action = Action::fromType((int)$row['action']);
                    if (($rollback && $action->equals(Action::SPAWN())) || (!$rollback && !$action->equals(Action::SPAWN()))) {
                        $id = (int)$row['entityfrom_id'];
                        $entity = $area->getWorld()->getEntity($id);
                        if ($entity !== null) {
                            $entity->close();
                        }
                    } else {
                        $logId = (int)$row['log_id'];
                        /**@var Entity $entityClass */
                        $entityClass = (string)$row['entity_classpath'];
                        $nbt = Utils::deserializeNBT($row['entityfrom_nbt']);
                        $entity = Entity::createEntity($entityClass::NETWORK_ID, $area->getWorld(), $nbt);
                        $this->updateEntityId($logId, $entity);
                        $entity->spawnToAll();
                    }
                }
            });
        }
    }

    protected final function updateEntityId(int $logId, Entity $entity): void
    {
        $this->connector->executeInsert(QueriesConst::UPDATE_ENTITY_ID, [
            'log_id' => $logId,
            'entity_id' => $entity->getId()
        ]);
    }
}