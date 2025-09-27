<?php

namespace Hibla\Task\Handlers;

use Hibla\Async\AsyncOperations;
use Hibla\Async\Exceptions\TimeoutException;
use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * Handles timeout functionality for async operations.
 *
 * This class provides the ability to run async operations with
 * a maximum execution time, throwing an exception if the timeout is exceeded.
 */
final readonly class TimeoutHandler
{
    /**
     * Async operations instance for timeout management.
     */
    private AsyncOperations $asyncOps;

    /**
     * Loop execution handler for running operations.
     */
    private LoopExecutionHandler $executionHandler;

    /**
     * Initialize the timeout handler.
     *
     * @param  AsyncOperations  $asyncOps  Async operations instance
     * @param  LoopExecutionHandler  $executionHandler  Loop execution handler
     */
    public function __construct(AsyncOperations $asyncOps, LoopExecutionHandler $executionHandler)
    {
        $this->asyncOps = $asyncOps;
        $this->executionHandler = $executionHandler;
    }

    /**
     * Run an async operations with a timeout limit.
     *
     * Executes the operation and races it against a timeout. If the operation
     * completes before the timeout, its result is returned. If the timeout
     * is reached first, an exception is thrown.
     *
     * @param  PromiseInterface<mixed>  $promise  Promise to timeout
     * @param  float  $seconds  Timeout in seconds
     * @return mixed The result of the async operation
     *
     * @throws TimeoutException If the operation times out
     */
    public function runWithTimeout(PromiseInterface $promise, float $seconds): mixed
    {
        return $this->executionHandler->run(function () use ($promise, $seconds) {
            return $this->asyncOps->await($this->asyncOps->timeout($promise, $seconds));
        });
    }
}
