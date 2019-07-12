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

use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Button;
use pocketmine\block\Door;
use pocketmine\block\Liquid;
use pocketmine\block\tile\Tile;
use pocketmine\nbt\tag\CompoundTag;

final class BlockUtils implements BlockLegacyIds{
	private function __construct(){
	}

	/**
	 * Returns true if the Block can be activated/interacted.
	 *
	 * @param Block $block
	 *
	 * @return bool
	 */
	public static function isActivable(Block $block) : bool{
		//It supports only PM-MP blocks.
		$ids = [
			self::TRAPDOOR, self::BED_BLOCK,
			self::ITEM_FRAME_BLOCK, self::IRON_TRAPDOOR, //Remove it when PM-MP supports redstone.
		];

		return (
			$block instanceof Door || $block instanceof Button || in_array($block->getId(), $ids) || self::hasInventory($block)
		);
	}

	/**
	 * Returns true if the block contains inventory.
	 *
	 * @param Block $block
	 *
	 * @return bool
	 */
	public static function hasInventory(Block $block) : bool{
		$ids = [
			self::ENDER_CHEST, self::CHEST,
			self::FURNACE, self::DISPENSER,
			self::ENCHANTING_TABLE, self::ANVIL,
			self::BREWING_STAND_BLOCK, self::HOPPER_BLOCK
		];

		return in_array($block->getId(), $ids);
	}

	/**
	 * Checks if the liquid is stilled.
	 *
	 * @param Liquid $liquid
	 *
	 * @return bool
	 */
	public static function isStillLiquid(Liquid $liquid) : bool{
		return $liquid->getId() === self::STILL_WATER || $liquid->getId() === self::STILL_LAVA;
	}

	/**
	 * Serialize a block (tile) NBT into base64. Returns null if block doesn't contain NBT.
	 *
	 * @param Block $block
	 *
	 * @return string|null
	 */
	public static function serializeBlockTileNBT(Block $block) : ?string{
		if(($tag = self::getCompoundTag($block)) !== null){
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
	public static function getCompoundTag(Block $block) : ?CompoundTag{
		if(($tile = self::asTile($block)) !== null){
			return $tile->getCleanedNBT();
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
	public static function asTile(Block $block) : ?Tile{
		if($block->getWorld() === null) return null;

		return $block->getWorld()->getTile($block->asPosition());
	}
}