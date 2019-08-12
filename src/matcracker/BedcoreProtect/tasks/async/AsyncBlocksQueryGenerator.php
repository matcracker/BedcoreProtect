<?php

declare(strict_types=1);

namespace matcracker\BedcoreProtect\tasks\async;

use ArrayOutOfBoundsException;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\PrimitiveBlock;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class AsyncBlocksQueryGenerator extends AsyncTask
{
    /**@var int $lastLogId */
    private $lastLogId;
    /**@var PrimitiveBlock[] $oldBlocks */
    private $oldBlocks;
    /**@var PrimitiveBlock[]|PrimitiveBlock $newBlocks */
    private $newBlocks;

    public function __construct(int $lastLogId, array $oldBlocks, $newBlocks)
    {
        $this->lastLogId = $lastLogId;
        $this->oldBlocks = $oldBlocks;
        $this->newBlocks = $newBlocks;
    }

    public function onRun(): void
    {
        $query = /**@lang text */
            "INSERT INTO blocks_log(history_id, old_block_id, old_block_meta, old_block_nbt, new_block_id, new_block_meta, new_block_nbt) VALUES";

        if (!is_array($this->newBlocks)) {
            $newId = $this->newBlocks->getId();
            $newMeta = $this->newBlocks->getMeta();
            $newNBT = $this->newBlocks->getSerializedNbt();

            foreach ($this->oldBlocks as $oldBlock) {
                $this->lastLogId++;
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();
                $query .= "('{$this->lastLogId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
            }
        } else {
            if (count($this->oldBlocks) !== count($this->newBlocks)) {
                throw new ArrayOutOfBoundsException("The number of old blocks must be the same as new blocks, or vice-versa");
            }

            foreach ($this->oldBlocks as $key => $oldBlock) {
                $this->lastLogId++;
                $newBlock = $this->newBlocks[$key];
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getMeta();
                $oldNBT = $oldBlock->getSerializedNbt();
                $newId = $newBlock->getId();
                $newMeta = $newBlock->getMeta();
                $newNBT = $newBlock->getSerializedNbt();

                $query .= "('{$this->lastLogId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
            }
        }
        $query = mb_substr($query, 0, -1) . ";";

        $this->setResult($query);
    }

    public function onCompletion(Server $server): void
    {
        /**@var Main $plugin */
        $plugin = $server->getPluginManager()->getPlugin(Main::PLUGIN_NAME);
        if ($plugin === null) {
            return;
        }
        $plugin->getDatabase()->getQueries()->insertRaw((string)$this->getResult());
    }
}