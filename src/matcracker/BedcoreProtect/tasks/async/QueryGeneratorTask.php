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

namespace matcracker\BedcoreProtect\tasks\async;

use Generator;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Utils;
use SOFe\AwaitGenerator\Await;
use UnexpectedValueException;
use function is_string;
use function strlen;

abstract class QueryGeneratorTask extends AsyncTask
{
    /** @var int */
    protected $logId;

    public function __construct(int $firstInsertedId, ?callable $onComplete)
    {
        $this->logId = $firstInsertedId;
        if ($onComplete !== null) {
            Utils::validateCallableSignature(
                function (string $query): Generator {
                    yield from [];
                },
                $onComplete
            );
            $this->storeLocal($onComplete);
        }
    }

    public function onCompletion(Server $server): void
    {
        $query = $this->getResult();
        if (!is_string($query) || strlen($query) === 0) {
            throw new UnexpectedValueException('The async task result does not a valid query string.');
        }

        /** @var callable|null $onComplete */
        $onComplete = $this->fetchLocal();

        if ($onComplete) {
            Await::f2c(
                static function () use ($query, $onComplete): Generator {
                    yield $onComplete($query);
                }
            );
        }
    }
}
