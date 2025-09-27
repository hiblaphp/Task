<?php

namespace Hibla\Task\Interfaces;

use Hibla\Async\Exceptions\TimeoutException;
use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * High-level operations for running async tasks with the event loop.
 *
 * This interface provides convenient methods for executing async operations,
 * managing concurrency, and performing common async patterns like timeouts
 * without writing to much boilerplate codes.
 */
interface LoopOperationsInterface
{
    /**
     * Runs a single async operation and returns its result.
     *
     * @param  callable|PromiseInterface<mixed>  $asyncOperation  The async operation to run
     * @return mixed The result of the async operation
     *
     * @throws \Exception Any exception thrown by the async operation
     */
    public function run(callable|PromiseInterface $asyncOperation): mixed;

    /**
     * Runs multiple async operations sequentially and returns all results.
     *
     * @param  array<int|string, callable|PromiseInterface<mixed>>  $asyncOperations  Array of callables or promises to execute
     * @return array<int|string, mixed> Array of results in the same order as input operations
     */
    public function runAll(array $asyncOperations): array;

    /**
     * Runs multiple async operations with controlled concurrency.
     *
     * @param  array<int|string, callable|PromiseInterface<mixed>>  $asyncOperations  Array of callables or promises to execute
     * @param  int  $concurrency  Maximum number of concurrent operations (default: 10)
     * @return array<int|string, mixed> Array of results in the same order as input operations
     */
    public function runConcurrent(array $asyncOperations, int $concurrency = 10): array;

    /**
     * Runs an async operation with a timeout.
     *
     * If the operation doesn't complete within the timeout period,
     * a timeout exception will be thrown.
     *
     * @param  PromiseInterface<mixed>  $promise  Promise to timeout
     * @param  float  $seconds  Timeout in seconds
     * @return mixed The result of the operation
     *
     * @throws TimeoutException If the operation times out
     */
    public function runWithTimeout(PromiseInterface $promise, float $seconds): mixed;
}
