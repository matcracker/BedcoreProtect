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

use matcracker\BedcoreProtect\utils\Action;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\object\FallingBlock;
use pocketmine\entity\object\Painting;
use pocketmine\event\entity\EntityBlockChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\player\Player;

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
        if ($this->configParser->isEnabledWorld($entity->getWorld()) && $this->configParser->getExplosions()) {
            $this->database->getQueries()->addBlocksLogByEntity($entity, $event->getBlockList(), VanillaBlocks::AIR(), Action::BREAK());
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
        if ($this->configParser->isEnabledWorld($entity->getWorld())) {
            if ($entity instanceof Painting && $this->configParser->getBlockPlace()) {
                $player = $entity->getWorld()->getNearestEntity($entity, 5, Player::class);
                if ($player !== null) {
                    $this->database->getQueries()->addLogEntityByEntity($player, $entity, Action::SPAWN());
                }
            } elseif ($entity instanceof FallingBlock && $this->configParser->getBlockMovement()) {
                $block = $entity->getBlock();

                $this->database->getQueries()->addBlockLogByEntity($entity, $block, VanillaBlocks::AIR(), Action::BREAK(), $entity->asPosition());
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
        if ($this->configParser->isEnabledWorld($entity->getWorld())) {
            if ($entity instanceof Painting && $this->configParser->getBlockBreak()) {
                $player = $entity->getWorld()->getNearestEntity($entity, 5, Player::class);
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
        if ($this->configParser->isEnabledWorld($entity->getWorld()) && $this->configParser->getEntityKills()) {
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
        if ($this->configParser->isEnabledWorld($entity->getWorld()) && $this->configParser->getBlockMovement()) {
            $this->database->getQueries()->addBlockLogByEntity($event->getEntity(), $event->getBlock(), $event->getTo(), Action::PLACE());
        }
    }
}