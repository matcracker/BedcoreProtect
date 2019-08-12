<?php

declare(strict_types=1);

namespace matcracker\BedcoreProtect\tasks\async;

use matcracker\BedcoreProtect\Main;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class AsyncInventoriesQueryGenerator extends AsyncTask
{
    /**@var string $query */
    private $query;

    public function __construct(string $query)
    {
        $this->query = $query;
    }

    public function onRun(): void
    {
        //TODO
    }

    public function onCompletion(Server $server): void
    {
        /**@var Main $plugin */
        $plugin = $server->getPluginManager()->getPlugin(Main::PLUGIN_NAME);
        if ($plugin === null) {
            return;
        }
        $plugin->getDatabase()->getQueries()->insertRaw((string)$this->query);
    }
}