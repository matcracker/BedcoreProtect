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
use pocketmine\block\BlockIds;
use pocketmine\entity\object\FallingBlock;
use pocketmine\entity\object\Painting;
use pocketmine\event\entity\EntityBlockChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\Player;

final class EntityListener extends BedcoreListener
{
    /**
     * @param EntityExplodeEvent $event
     *
     * @priority MONITOR
     */
    public function trackEntityExplode(EntityExplodeEvent $event): void
    {
        $entity = $event->getEntity();
        if ($this->plugin->getParsedConfig()->isEnabledWorld($entity->getLevel()) && $this->plugin->getParsedConfig()->getExplosions()) {
            $this->database->getQueries()->addBlocksLogByEntity($entity, $event->getBlockList(), BlockFactory::get(BlockIds::AIR), Action::BREAK());
        }
    }

    /**
     * @param EntitySpawnEvent $event
     *
     * @priority MONITOR
     */
    public function trackEntitySpawn(EntitySpawnEvent $event): void
    {
        $entity = $event->getEntity();
        if ($this->plugin->getParsedConfig()->isEnabledWorld($entity->getLevel())) {
            if ($entity instanceof Painting && $this->plugin->getParsedConfig()->getBlockPlace()) {
                $player = $entity->getLevel()->getNearestEntity($entity, 5, Player::class);
                if ($player !== null) {
                    $this->database->getQueries()->addLogEntityByEntity($player, $entity, Action::SPAWN());
                }
            } elseif ($entity instanceof FallingBlock && $this->plugin->getParsedConfig()->getBlockMovement()) {
                $block = BlockFactory::get($entity->getBlock());

                $this->database->getQueries()->addBlockLogByEntity($entity, $block, BlockFactory::get(BlockIds::AIR), Action::BREAK(), $entity->asPosition());
            }
        }
    }

    /**
     * @param EntityDespawnEvent $event
     *
     * @priority MONITOR
     */
    public function trackEntityDespawn(EntityDespawnEvent $event): void
    {
        $entity = $event->getEntity();
        if ($this->plugin->getParsedConfig()->isEnabledWorld($entity->getLevel())) {
            if ($entity instanceof Painting && $this->plugin->getParsedConfig()->getBlockBreak()) {
                $player = $entity->getLevel()->getNearestEntity($entity, 5, Player::class);
                if ($player !== null) {
                    $this->database->getQueries()->addLogEntityByEntity($player, $entity, Action::DESPAWN());
                }
            }
        }
    }

    /**
     * @param EntityDeathEvent $event
     *
     * @priority MONITOR
     */
    public function trackEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        if ($this->plugin->getParsedConfig()->isEnabledWorld($entity->getLevel()) && $this->plugin->getParsedConfig()->getEntityKills()) {
            $ev = $entity->getLastDamageCause();
            if ($ev instanceof EntityDamageByEntityEvent) {
                $damager = $ev->getDamager();
                $this->database->getQueries()->addLogEntityByEntity($damager, $entity, Action::KILL());
            }
        }
    }

    /**
     * @param EntityBlockChangeEvent $event
     *
     * @priority MONITOR
     */
    public function trackEntityBlockChange(EntityBlockChangeEvent $event): void
    {
        $entity = $event->getEntity();
        if ($this->plugin->getParsedConfig()->isEnabledWorld($entity->getLevel()) && $this->plugin->getParsedConfig()->getBlockMovement()) {
            $this->database->getQueries()->addBlockLogByEntity($event->getEntity(), $event->getBlock(), $event->getTo(), Action::PLACE());
        }
    }
}