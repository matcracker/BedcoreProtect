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
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\event\block\LeavesDecayEvent;

final class WorldListener extends BedcoreListener{
	/**
	 * @param LeavesDecayEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackLeavesDecay(LeavesDecayEvent $event) : void{
		$block = $event->getBlock();
		if($this->configParser->isEnabledWorld($block->getWorld()) && $this->configParser->getLeavesDecay()){
			$this->database->getQueries()->addBlockLogByBlock($block, $block, BlockUtils::getAir($block->asPosition()), Action::BREAK());
		}
	}

}