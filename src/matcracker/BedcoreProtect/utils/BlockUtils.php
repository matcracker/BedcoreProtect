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
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Door;
use pocketmine\block\Liquid;
use pocketmine\world\Position;

final class BlockUtils implements BlockLegacyIds{
	private function __construct(){
	}

	public static function createAir(?Position $position = null) : Block{
		return BlockFactory::get(self::AIR, 0, $position);
	}

	public static function isActivable(Block $block) : bool{
		//It supports only PMMP blocks.
		$ids = [
			self::TRAPDOOR, self::BED_BLOCK,
			self::ITEM_FRAME_BLOCK, self::WOODEN_BUTTON,
			self::STONE_BUTTON
		];

		return (
			$block instanceof Door || in_array($block->getId(), $ids) || self::hasInventory($block)
		);
	}

	public static function hasInventory(Block $block) : bool{
		//It supports only PMMP blocks.
		$ids = [
			self::ENDER_CHEST, self::CHEST,
			self::FURNACE, self::DISPENSER,
			self::ENCHANTING_TABLE, self::ANVIL
		];

		return in_array($block->getId(), $ids);
	}

	public static function isStillLiquid(Liquid $liquid) : bool{
		return $liquid->getId() === self::STILL_WATER || $liquid->getId() === self::STILL_LAVA;
	}
}