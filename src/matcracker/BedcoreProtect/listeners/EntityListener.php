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

namespace matcracker\BedcoreProtect\listeners;

use matcracker\BedcoreProtect\enums\Action;
use pocketmine\block\BlockFactory;
use pocketmine\entity\Human;
use pocketmine\entity\object\FallingBlock;
use pocketmine\entity\object\Painting;
use pocketmine\event\entity\EntityBlockChangeEvent;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntitySpawnEvent;

final class EntityListener extends BedcoreListener
{
    /**
     * @param EntityExplodeEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackEntityExplode(EntityExplodeEvent $event): void
    {
        $entity = $event->getEntity();
        if ($this->config->isEnabledWorld($entity->getLevelNonNull()) && $this->config->getExplosions()) {
            $this->blocksQueries->addBlocksLogByEntity($entity, $event->getBlockList(), [$this->air], Action::BREAK());
        }
    }

    /**
     * @param EntitySpawnEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackEntitySpawn(EntitySpawnEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof Human) {
            return;
        }

        $level = $entity->getLevelNonNull();
        if ($this->config->isEnabledWorld($level)) {
            if ($entity instanceof FallingBlock && $this->config->getBlockMovement()) {
                $this->blocksQueries->addBlockLogByEntity($entity, BlockFactory::get($entity->getBlock(), $entity->getDamage()), $this->air, Action::BREAK(), $entity->asPosition());

            }
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackEntityDamageByEntity(EntityDamageByEntityEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof Human) {
            return;
        }

        if ($this->config->isEnabledWorld($entity->getLevelNonNull())) {
            if ($entity instanceof Painting && $this->config->getBlockBreak()) {
                $damager = $event->getDamager();
                if ($damager !== null) {
                    $this->entitiesQueries->addEntityLogByEntity($damager, $entity, Action::DESPAWN());
                }
            }
        }
    }

    /**
     * @param EntityDeathEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        if ($entity instanceof Human) {
            return;
        }

        if ($this->config->isEnabledWorld($entity->getLevelNonNull()) && $this->config->getEntityKills()) {
            $damageEvent = $entity->getLastDamageCause();
            if ($damageEvent instanceof EntityDamageByEntityEvent) {
                $damager = $damageEvent->getDamager();
                if ($damager !== null) {
                    $this->entitiesQueries->addEntityLogByEntity($damager, $entity, Action::KILL());
                }
            } elseif ($damageEvent instanceof EntityDamageByBlockEvent) {
                $this->entitiesQueries->addEntityLogByBlock($entity, $damageEvent->getDamager(), Action::KILL());
            }
        }
    }

    /**
     * @param EntityBlockChangeEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackEntityBlockChange(EntityBlockChangeEvent $event): void
    {
        $entity = $event->getEntity();
        if ($this->config->isEnabledWorld($entity->getLevelNonNull()) && $this->config->getBlockMovement()) {
            $this->blocksQueries->addBlockLogByEntity($entity, $event->getBlock(), $event->getTo(), Action::PLACE(), $entity->asPosition());
        }
    }
}
