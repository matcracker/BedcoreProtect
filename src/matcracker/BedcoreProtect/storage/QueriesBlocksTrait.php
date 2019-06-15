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

namespace matcracker\BedcoreProtect\storage;

use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\SignPost;
use pocketmine\block\WallSign;
use pocketmine\entity\Entity;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\tile\Sign;
use pocketmine\tile\Tile;
use poggit\libasynql\SqlError;

/**
 * It contains all the queries methods related to blocks.
 *
 * Trait QueriesBlocksTrait
 * @package matcracker\BedcoreProtect\storage
 */
trait QueriesBlocksTrait
{

    /**
     * It logs the entity who mades the action for block.
     *
     * @param Entity $entity
     * @param Block $oldBlock
     * @param Block $newBlock
     * @param int $action
     * @param Position|null $position
     */
    public function addBlockLogByEntity(Entity $entity, Block $oldBlock, Block $newBlock, int $action, ?Position $position = null): void
    {
        $uuid = ($entity instanceof Player) ? $entity->getUniqueId()->toString() : strval($entity::NETWORK_ID);
        $this->addEntity($entity);
        $this->addRawBlockLog($uuid, $oldBlock, $newBlock, $action, $position);
    }

    private function addRawBlockLog(string $uuid, Block $oldBlock, Block $newBlock, int $action, ?Position $position = null): void
    {
        $this->addBlock($oldBlock);
        $this->addBlock($newBlock);

        $pos = $position ?? $newBlock->asPosition();
        $this->addRawLog($uuid, $pos, $action);
        $this->connector->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
            "old_id" => $oldBlock->getId(),
            "old_damage" => $oldBlock->getDamage(),
            "new_id" => $newBlock->getId(),
            "new_damage" => $newBlock->getDamage()
        ]);
    }

    /**
     * It registers the block inside 'blocks' table
     * @param Block $block
     */
    public function addBlock(Block $block): void
    {
        $this->connector->executeInsert(QueriesConst::ADD_BLOCK, [
            "id" => $block->getId(),
            "damage" => $block->getDamage(),
            "name" => $block->getName()
        ]);
    }

    /**
     * It logs the block who mades the action for block.
     *
     * @param Block $oldBlock
     * @param Block $newBlock
     * @param int $action
     * @param Position|null $position
     */
    public function addBlockLogByBlock(Block $oldBlock, Block $newBlock, int $action, ?Position $position = null): void
    {
        $this->addRawBlockLog((strtolower($newBlock->getName()) . "-uuid"), $oldBlock, $newBlock, $action, $position);
    }

    /**
     * It logs the text and position of the removed sign.
     *
     * @param Player $player
     * @param Sign $sign
     */
    public function addSignLogByPlayer(Player $player, Sign $sign): void
    {
        $air = BlockUtils::createAir($sign->asPosition());
        $signBlock = $sign->getBlock();

        $this->addRawBlockLog($player->getUniqueId()->toString(), $signBlock, $air, QueriesConst::BROKE);
        $this->connector->executeInsert(QueriesConst::ADD_SIGN_LOG, [
            "lines" => json_encode($sign->getText())
        ]);
    }

    /**
     * @param Entity $entity
     * @param Block[] $oldBlocks
     * @param Block[]|Block $newBlocks
     * @param int $action
     */
    public function addBlocksLogByEntity(Entity $entity, array $oldBlocks, $newBlocks, int $action): void
    {
        $this->addEntity($entity);

        $oldBlocksQuery = $this->buildMultipleBlocksQuery($oldBlocks);
        $this->connector->executeInsertRaw($oldBlocksQuery);

        if (is_array($newBlocks)) {
            (function (Block ...$_) {
            })(... $newBlocks);
            $newBlocksQuery = $this->buildMultipleBlocksQuery($newBlocks);
            $this->connector->executeInsertRaw($newBlocksQuery);
        } else {
            $this->addBlock($newBlocks);
        }

        $rawLogsQuery = $this->buildMultipleRawLogsQuery(Utils::getEntityUniqueId($entity), $oldBlocks, $action);
        $rawBlockLogsQuery = $this->buildMultipleRawBlockLogsQuery($oldBlocks, $newBlocks);

        $this->connector->executeInsertRaw($rawLogsQuery);
        $this->connector->executeInsertRaw($rawBlockLogsQuery);
    }

    /**
     * @param Block[] $blocks
     * @return string
     */
    private function buildMultipleBlocksQuery(array $blocks): string
    {
        $query = /**@lang text */
            "REPLACE INTO blocks (id, damage, block_name) VALUES";

        $filtered = array_unique(array_map(function (Block $element) {
            return $element->getId() . ":" . $element->getDamage() . ":" . $element->getName();
        }, $blocks));

        foreach ($filtered as $value) {
            $blockData = explode(":", $value);
            $id = (int)$blockData[0];
            $damage = (int)$blockData[1];
            $name = (string)$blockData[2];
            $query .= "('$id', '$damage', '$name'),";
        }
        $query = rtrim($query, ",") . ";";//" ON DUPLICATE KEY UPDATE id=VALUES(id), damage=VALUES(damage);";
        return $query;
    }

    /**
     * @param array $oldBlocks
     * @param Block[]|Block $newBlocks
     * @return string
     */
    private function buildMultipleRawBlockLogsQuery(array $oldBlocks, $newBlocks): string
    {
        $query = /**@lang text */
            "INSERT INTO blocks_log(history_id, old_block_id, old_block_damage, new_block_id, new_block_damage) VALUES";

        $logId = $this->getLastLogId();

        if (!is_array($newBlocks) && $newBlocks instanceof Block) {
            $newId = $newBlocks->getId();
            $newDamage = $newBlocks->getDamage();

            foreach ($oldBlocks as $oldBlock) {
                $logId++;
                $oldId = $oldBlock->getId();
                $oldDamage = $oldBlock->getDamage();
                $query .= "('$logId', (SELECT id FROM blocks WHERE blocks.id = '$oldId' AND damage = '$oldDamage'),
                (SELECT damage FROM blocks WHERE blocks.id = '$oldId' AND damage = '$oldDamage'),
                (SELECT id FROM blocks WHERE blocks.id = '$newId' AND damage = '$newDamage'),
                (SELECT damage FROM blocks WHERE blocks.id = '$newId' AND damage = '$newDamage')),";
            }
        } else {
            foreach ($oldBlocks as $key => $oldBlock) {
                $logId++;
                $oldId = $oldBlock->getId();
                $oldDamage = $oldBlock->getDamage();
                $newId = $newBlocks[$key]->getId();
                $newDamage = $newBlocks[$key]->getDamage();

                $query .= "('$logId', (SELECT id FROM blocks WHERE blocks.id = '$oldId' AND damage = '$oldDamage'),
                (SELECT damage FROM blocks WHERE blocks.id = '$oldId' AND damage = '$oldDamage'),
                (SELECT id FROM blocks WHERE blocks.id = '$newId' AND damage = '$newDamage'),
                (SELECT damage FROM blocks WHERE blocks.id = '$newId' AND damage = '$newDamage')),";
            }
        }
        $query = rtrim($query, ",") . ";";
        return $query;
    }

    protected function rollbackBlocks(Position $position, CommandParser $parser, ?callable $onSuccessRollback = null, ?callable $onError = null): void
    {
        $this->executeBlocksEdit(true, $position, $parser, $onSuccessRollback, $onError);
    }

    private function executeBlocksEdit(bool $rollback, Position $position, CommandParser $parser, ?callable $onSuccess = null, ?callable $onError = null): void
    {
        $query = $parser->buildBlocksLogSelectionQuery($position, !$rollback);
        $this->connector->executeSelectRaw($query, [],
            function (array $rows) use ($rollback, $position, $onSuccess, $parser) {
                if (count($rows) > 0) {
                    $query = /**@lang text */
                        "UPDATE log_history SET rollback = '{$rollback}' WHERE ";

                    foreach ($rows as $row) {
                        $level = $position->getLevel();
                        $logId = (int)$row["log_id"];
                        $prefix = $rollback ? "old" : "new";
                        $block = BlockFactory::get((int)$row["{$prefix}_block_id"], (int)$row["{$prefix}_block_damage"]);
                        $vector = new Vector3((int)$row["x"], (int)$row["y"], (int)$row["z"]);
                        $level->setBlock($vector, $block, false, true);

                        if ($block instanceof SignPost) {
                            $face = $block instanceof WallSign ? $block->getDamage() : Vector3::SIDE_UP;
                            /**@var Sign $sign */
                            $sign = Tile::createTile(Tile::SIGN, $level, Sign::createNBT($vector, $face));
                            if ($this->configParser->getSignText()) {
                                $this->connector->executeSelect(QueriesConst::GET_SIGN_LOG, ["id" => $logId],
                                    function (array $rows) use ($sign) {
                                        if (count($rows) === 1) {
                                            $texts = (array)json_decode($rows[0]["text_lines"], true);
                                            $sign->setText($texts[0], $texts[1], $texts[2], $texts[3]);
                                        }
                                    }
                                );
                            }
                        }

                        $query .= "log_id = '$logId' OR ";
                    }

                    $query = rtrim($query, " OR ") . ";";
                    $this->connector->executeInsertRaw($query);
                }

                if ($onSuccess !== null) {
                    $onSuccess(count($rows), $parser);
                }
            },
            function (SqlError $error) use ($onError) {
                if ($onError !== null) {
                    $onError($error);
                }
            }
        );
    }

    protected function restoreBlocks(Position $position, CommandParser $parser, ?callable $onSuccessRestore = null, ?callable $onError = null): void
    {
        $this->executeBlocksEdit(false, $position, $parser, $onSuccessRestore, $onError);
    }
}