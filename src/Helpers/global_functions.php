<?php

use Hibla\Async\Exceptions\TimeoutException;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Task\Task;

if (! function_exists('run')) {
    /**
     * Run an async operation with automatic event loop management.
     *
     * This function handles the complete lifecycle: starts the event loop,
     * executes the operation, waits for completion, and stops the loop.
     * This is the primary method for running async operations with minimal setup.
     *
     * @param  callable(): mixed|PromiseInterface<mixed>  $asyncOperation  The operation to execute.
     * @return mixed The result of the async operation.
     */
    function run(callable|PromiseInterface $asyncOperation, bool $resetEventLoop = true): mixed
    {
        return Task::run($asyncOperation, $resetEventLoop);
    }
}

if (! function_exists('run_stateful')) {
    /**
     * Run an async operation with automatic event loop management without resetting event loop
     * for persistent and stateful connections.
     *
     * This function handles the complete lifecycle: starts the event loop,
     * executes the operation, waits for completion, and stops the loop.
     * This is the primary method for running async operations with minimal setup.
     *
     * @param  callable(): mixed|PromiseInterface<mixed>  $asyncOperation  The operation to execute.
     * @return mixed The result of the async operation.
     */
    function run_stateful(callable|PromiseInterface $asyncOperation): mixed
    {
        return Task::runStateful($asyncOperation);
    }
}

if (! function_exists('run_all')) {
    /**
     * Run multiple async operations concurrently with automatic loop management.
     *
     * Starts all operations simultaneously, waits for all to complete, then
     * returns their results in the same order as the input array. The event
     * loop is managed automatically throughout the entire process.
     *
     * @param  array<int|string, callable(): mixed|PromiseInterface<mixed>>  $asyncOperations  Array of callables or promises to execute.
     * @return array<mixed> Results of all operations in the same order as input.
     */
    function run_all(array $asyncOperations): array
    {
        return Task::runAll($asyncOperations);
    }
}

if (! function_exists('run_concurrent')) {
    /**
     * Run async operations with concurrency control and automatic loop management.
     *
     * Executes operations in controlled batches to prevent system overload while
     * maintaining high throughput. The event loop lifecycle is handled automatically,
     * making this ideal for processing large numbers of operations safely.
     *
     * @param  array<int|string, callable(): mixed|PromiseInterface<mixed>>  $asyncOperations  Array of operations to execute.
     * @param  int  $concurrency  Maximum number of concurrent operations.
     * @return array<mixed> Results of all operations.
     */
    function run_concurrent(array $asyncOperations, int $concurrency = 10): array
    {
        return Task::runConcurrent($asyncOperations, $concurrency);
    }
}

if (! function_exists('run_with_timeout')) {
    /**
     * Run an async operation with a timeout constraint and automatic loop management.
     *
     * Executes the operation with a maximum time limit. If the operation doesn't
     * complete within the timeout, it's cancelled and a timeout exception is thrown.
     * The event loop is managed automatically throughout.
     *
     * @param  PromiseInterface<mixed>  $promise  Promise to timeout
     * @param  float  $seconds  Maximum time to wait in seconds.
     * @return mixed The result of the operation if completed within timeout.
     *
     * @throws TimeoutException If the operation times out.
     */
    function run_with_timeout(PromiseInterface $promise, float $seconds): mixed
    {
        return Task::runWithTimeout($promise, $seconds);
    }
}

if (! function_exists('run_batch')) {
    /**
     * Run async operations in batches with concurrency control and automatic loop management.
     *
     * @param  array<int|string, callable(): mixed|PromiseInterface<mixed>>  $asyncOperations  Array of operations to execute.
     * @param  int  $batch  Number of operations to run in each batch.
     * @param  int|null  $concurrency  Maximum number of concurrent operations per batch.
     * @return array<mixed> Results of all operations.
     */
    function run_batch(array $asyncOperations, int $batch, ?int $concurrency = null): array
    {
        return Task::runBatch($asyncOperations, $batch, $concurrency);
    }
}

if (! function_exists('run_all_settled')) {
    /**
     * Run multiple async operations concurrently and wait for all to settle.
     *
     * Unlike run_all(), this method waits for every operation to complete and returns
     * all results, including both successful values and rejection reasons.
     * This function never throws - it always returns settlement results.
     *
     * @param  array<int|string, callable(): mixed|PromiseInterface<mixed>>  $asyncOperations  Array of callables or promises to execute.
     * @return array<int|string, array{status: 'fulfilled'|'rejected', value?: mixed, reason?: mixed}> Settlement results of all operations.
     */
    function run_all_settled(array $asyncOperations): array
    {
        return Task::runAllSettled($asyncOperations);
    }
}

if (! function_exists('run_concurrent_settled')) {
    /**
     * Run async operations with concurrency control and wait for all to settle.
     *
     * Similar to run_concurrent(), but waits for all operations to complete and returns
     * settlement results for all operations. This function never throws - it always
     * returns settlement results.
     *
     * @param  array<int|string, callable(): mixed|PromiseInterface<mixed>>  $asyncOperations  Array of operations to execute.
     * @param  int  $concurrency  Maximum number of concurrent operations.
     * @return array<int|string, array{status: 'fulfilled'|'rejected', value?: mixed, reason?: mixed}> Settlement results of all operations.
     */
    function run_concurrent_settled(array $asyncOperations, int $concurrency = 10): array
    {
        return Task::runConcurrentSettled($asyncOperations, $concurrency);
    }
}

if (! function_exists('run_batch_settled')) {
    /**
     * Run async operations in batches with concurrency control and wait for all to settle.
     *
     * Similar to run_batch(), but waits for all operations to complete and returns
     * settlement results for all operations. This function never throws - it always
     * returns settlement results.
     *
     * @param  array<int|string, callable(): mixed|PromiseInterface<mixed>>  $asyncOperations  Array of operations to execute.
     * @param  int  $batch  Number of operations to run in each batch.
     * @param  int|null  $concurrency  Maximum number of concurrent operations per batch.
     * @return array<int|string, array{status: 'fulfilled'|'rejected', value?: mixed, reason?: mixed}> Settlement results of all operations.
     */
    function run_batch_settled(array $asyncOperations, int $batch, ?int $concurrency = null): array
    {
        return Task::runBatchSettled($asyncOperations, $batch, $concurrency);
    }
}
