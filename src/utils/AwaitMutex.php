<?php

/*
 * Inspired by https://github.com/SOF3/rwlock.php
 *
 * Copyright 2021 SOFe
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

declare(strict_types=1);

namespace matcracker\BedcoreProtect\utils;

use Closure;
use Generator;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use function array_shift;
use function count;

final class AwaitMutex
{
    private bool $running = false;
    /** @var Closure[] */
    private array $queue = [];

    /**
     * @param Closure $promise
     * @param callable|null $onSuccess
     * @param callable|callable[] $onError
     */
    public function putClosure(Closure $promise, ?callable $onSuccess = null, callable|array $onError = []): void
    {
        $this->put($promise(), $onSuccess, $onError);
    }

    /**
     * @param Generator $promise
     * @param callable|null $onSuccess
     * @param callable|callable[] $onError
     */
    public function put(Generator $promise, ?callable $onSuccess = null, callable|array $onError = []): void
    {
        $this->queue[] = function () use ($promise, $onSuccess, $onError): void {
            Await::g2c(
                $promise,
                function () use ($onSuccess): void {
                    $this->running = false;
                    if ($onSuccess !== null) {
                        $onSuccess();
                    }
                    $this->next();
                },
                $onError
            );
        };

        if (!$this->running) {
            $this->next();
        }
    }

    private function next(): void
    {
        if ($this->running) {
            throw new RuntimeException("Call to next() while still running");
        }

        $runnable = array_shift($this->queue);
        if ($runnable !== null) {
            $this->running = true;
            $runnable();
        }
    }

    /**
     * Returns true if the mutex is currently unused, false if it is currently locked.
     */
    public function isIdle(): bool
    {
        return !$this->running;
    }

    /**
     * Returns the number of async functions that the mutex is scheduled to run,
     * excluding the currently executing one.
     */
    public function getQueueLength(): int
    {
        return count($this->queue);
    }
}