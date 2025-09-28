<?php

namespace Hibla\Task\Handlers;

use Exception;
use Hibla\Async\AsyncOperations;
use Hibla\EventLoop\EventLoop;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use RuntimeException;
use Throwable;

final class LoopExecutionHandler
{
    private AsyncOperations $asyncOps;
    private static bool $isRunning = false;

    public function __construct(AsyncOperations $asyncOps)
    {
        $this->asyncOps = $asyncOps;
    }

    /**
     * @param  callable|PromiseInterface<mixed>  $asyncOperation
     */
    public function run(callable|PromiseInterface $asyncOperation, bool $resetEventLoop = true): mixed
    {
        if (self::$isRunning) {
            throw new RuntimeException('Cannot call run() while already running. Use await() instead.');
        }

        try {
            self::$isRunning = true;
            $result = null;
            $error = null;
            $completed = false;

            $promise = is_callable($asyncOperation)
                ? $this->asyncOps->async($asyncOperation)()
                : $asyncOperation;

            $promise
                ->then(function ($value) use (&$result, &$completed) {
                    $result = $value;
                    $completed = true;
                })
                ->catch(function ($reason) use (&$error, &$completed) {
                    $error = $reason;
                    $completed = true;
                })
            ;

            while (! $completed) {
                EventLoop::getInstance()->run();
            }

            if ($error !== null) {
                throw $error instanceof Throwable ? $error : new Exception($this->safeStringCast($error));
            }

            return $result;
        } finally {
            self::$isRunning = false;
            if ($resetEventLoop) {
                EventLoop::reset();
            }
        }
    }

    /**
     * @param  callable|PromiseInterface<mixed>  $operation
     * @return PromiseInterface<mixed>
     */
    public function createPromiseFromOperation(callable|PromiseInterface $operation): PromiseInterface
    {
        if (is_callable($operation)) {
            return new Promise(function (callable $resolve, callable $reject) use ($operation) {
                try {
                    $asyncTask = $this->asyncOps->async($operation);
                    $result = $asyncTask();

                    // If the callable returns a PromiseInterface, await it
                    if ($result instanceof PromiseInterface) {
                        $awaitedResult = await($result);
                        $resolve($awaitedResult);
                    } else {
                        // If it's not a promise, resolve with the direct result
                        $resolve($result);
                    }
                } catch (Throwable $e) {
                    $reject($e);
                }
            });
        }

        return $operation;
    }

    /**
     * Safely convert mixed value to string for error messages
     */
    private function safeStringCast(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_null($value) => 'null',
            is_scalar($value) => (string) $value,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            is_array($value) => 'Array: '.json_encode($value),
            is_object($value) => 'Object: '.get_class($value),
            default => 'Unknown error type: '.gettype($value)
        };
    }
}
