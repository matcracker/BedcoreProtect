<?php

/*
 * BedcoreProtect
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


use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\block\Door;
use pocketmine\block\Liquid;
use pocketmine\level\Position;

final class BlockUtils implements BlockIds
{
    private function __construct()
    {
    }

    public static function createAir(?Position $position = null): Block
    {
        return BlockFactory::get(self::AIR, 0, $position);
    }

    public static function isActivable(Block $block): bool
    {
        //It supports only PMMP blocks.
        $ids = [
            BlockIds::TRAPDOOR, BlockIds::BED_BLOCK,
            BlockIds::ITEM_FRAME_BLOCK, BlockIds::WOODEN_BUTTON,
            BlockIds::STONE_BUTTON
        ];
        return (
            $block instanceof Door || in_array($block->getId(), $ids) || self::hasInventory($block)
        );
    }

    public static function hasInventory(Block $block): bool
    {
        //It supports only PMMP blocks.
        $ids = [
            BlockIds::ENDER_CHEST, BlockIds::CHEST,
            BlockIds::FURNACE, BlockIds::DISPENSER,
            BlockIds::ENCHANTING_TABLE, BlockIds::ANVIL
        ];
        return in_array($block->getId(), $ids);
    }

    public static function isStillLiquid(Liquid $liquid): bool
    {
        return $liquid->getId() === BlockIds::STILL_WATER || $liquid->getId() === BlockIds::STILL_LAVA;
    }
}