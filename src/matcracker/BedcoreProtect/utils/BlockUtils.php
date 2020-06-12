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

namespace matcracker\BedcoreProtect\utils;

use pocketmine\block\Anvil;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\block\BrewingStand;
use pocketmine\block\BurningFurnace;
use pocketmine\block\Button;
use pocketmine\block\Chest;
use pocketmine\block\Door;
use pocketmine\block\EnchantingTable;
use pocketmine\block\FenceGate;
use pocketmine\block\ItemFrame;
use pocketmine\block\Lever;
use pocketmine\block\Trapdoor;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\tile\Tile;
use function is_a;

final class BlockUtils
{
    private function __construct()
    {
    }

    /**
     * Returns true if the Block can be clicked
     *
     * @param Block $block
     *
     * @return bool
     */
    public static function canBeClicked(Block $block): bool
    {
        static $blockClasses = [
            Door::class, Trapdoor::class,//Remove Iron Trapdoor and Door classes when PM-MP supports redstone.
            ItemFrame::class, Button::class,
            Lever::class, FenceGate::class
        ];

        foreach ($blockClasses as $blockClass) {
            if (is_a($block, $blockClass)) {
                return true;
            }
        }

        return self::hasInventory($block);
    }

    /**
     * Returns true if the block contains inventory.
     *
     * @param Block $block
     *
     * @return bool
     */
    public static function hasInventory(Block $block): bool
    {
        static $blockClasses = [
            Chest::class, BurningFurnace::class,
            EnchantingTable::class, Anvil::class,
            BrewingStand::class
        ];

        foreach ($blockClasses as $blockClass) {
            if (is_a($block, $blockClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Serializes a block (tile) compound tag into base64. Returns null if block doesn't contain NBT.
     *
     * @param Block $block
     *
     * @return string|null
     */
    public static function serializeTileTag(Block $block): ?string
    {
        if (($tag = self::getCompoundTag($block)) !== null) {
            return Utils::serializeNBT($tag);
        }

        return null;
    }

    /**
     * Returns the CompoundTag of block if it exists, else returns null.
     *
     * @param Block $block
     *
     * @return CompoundTag|null
     */
    public static function getCompoundTag(Block $block): ?CompoundTag
    {
        if (($tile = self::asTile($block)) !== null) {
            return clone $tile->saveNBT();
        }

        return null;
    }


    /**
     * Returns a Tile instance of the given block if it exists.
     *
     * @param Block $block
     *
     * @return Tile|null
     */
    public static function asTile(Block $block): ?Tile
    {
        if (($level = $block->getLevel()) === null) {
            return null;
        }

        return $level->getTile($block->asVector3());
    }

    /**
     * @param int $blockId
     * @return string
     */
    public static function getTileName(int $blockId): string //Remove on 4.0
    {
        static $array = [
            BlockIds::STANDING_BANNER => Tile::BANNER,
            BlockIds::WALL_BANNER => Tile::BANNER,
            BlockIds::BED_BLOCK => Tile::BED,
            BlockIds::BREWING_STAND_BLOCK => Tile::BREWING_STAND,
            BlockIds::CHEST => Tile::CHEST,
            BlockIds::TRAPPED_CHEST => Tile::CHEST,
            BlockIds::ENCHANTING_TABLE => Tile::ENCHANT_TABLE,
            BlockIds::ENDER_CHEST => Tile::ENDER_CHEST,
            BlockIds::FLOWER_POT_BLOCK => Tile::FLOWER_POT,
            BlockIds::FURNACE => Tile::FURNACE,
            BlockIds::ITEM_FRAME_BLOCK => Tile::ITEM_FRAME,
            BlockIds::SIGN_POST => Tile::SIGN,
            BlockIds::WALL_SIGN => Tile::SIGN,
            BlockIds::SKULL_BLOCK => Tile::SKULL
        ];

        return $array[$blockId] ?? 'Unknown';
    }
}
