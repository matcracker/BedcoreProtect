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

namespace matcracker\BedcoreProtect\utils;

use pocketmine\block\Block;
use pocketmine\block\Button;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Fire;
use pocketmine\block\ItemFrame;
use pocketmine\block\Leaves;
use pocketmine\block\Lever;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Tile;
use pocketmine\block\Trapdoor;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function is_a;

final class BlockUtils
{
    private function __construct()
    {
    }

    public static function getUniqueId(Block $block): string
    {
        return GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getName();
    }

    public static function getAliasName(Block $block): string
    {
        if ($block instanceof Leaves) {
            $name = "#Decay";
        } elseif ($block instanceof Fire) {
            $name = "#Fire";
        } else {
            $name = "#{$block->getName()}";
        }

        return $name;
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
        return self::asTile($block) instanceof Container;
    }

    /**
     * Returns a Tile instance of the given block if it exists.
     *
     * @param Block $block
     * @return Tile|null
     */
    public static function asTile(Block $block): ?Tile
    {
        $position = $block->getPosition();
        if ($position->isValid()) {
            return $position->getWorld()->getTileAt($position->x, $position->y, $position->z);
        } else {
            return null;
        }
    }

    /**
     * Serializes a block state data compound tag into base64.
     *
     * @param Block $block
     *
     * @return string
     */
    public static function serializeBlock(Block $block): string
    {
        return Utils::serializeNBT(GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->toNbt());
    }

    /**
     * Serializes a block state data compound tag into base64.
     *
     * @param string $encodedState
     * @return Block
     */
    public static function deserializeBlock(string $encodedState): Block
    {
        $state = BlockStateData::fromNbt(Utils::deserializeNBT($encodedState));

        return GlobalBlockStateHandlers::getDeserializer()->deserializeBlock($state);
    }

    /**
     * Serializes a block (tile) compound tag into base64. Returns null if block doesn't contain NBT.
     *
     * @param Block $block
     *
     * @return string|null
     *
     * @deprecated @see Utils::serializeNBT()
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
        return self::asTile($block)?->saveNBT();
    }
}
