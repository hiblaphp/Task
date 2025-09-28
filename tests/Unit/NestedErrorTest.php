<?php

use Hibla\Async\AsyncOperations;
use Hibla\Promise\Promise;
use Hibla\Task\Handlers\LoopExecutionHandler;

beforeEach(function () {
    resetEventLoop();
});

it('throws RuntimeException when run() is called while already running', function () {
    $asyncOps = new AsyncOperations();
    $handler = new LoopExecutionHandler($asyncOps);

    $outerOperation = function () use ($handler) {
        return $handler->run(function () {
            return 'inner result';
        });
    };

    expect(fn () => $handler->run($outerOperation))
        ->toThrow(RuntimeException::class, 'Cannot call run() while already running. Use await() instead.')
    ;
});

it('throws RuntimeException when run() is called with promise while already running', function () {
    $asyncOps = new AsyncOperations();
    $handler = new LoopExecutionHandler($asyncOps);

    $outerOperation = function () use ($handler) {
        $innerPromise = new Promise(function ($resolve) {
            $resolve('inner result');
        });

        return $handler->run($innerPromise);
    };

    expect(fn () => $handler->run($outerOperation))
        ->toThrow(RuntimeException::class, 'Cannot call run() while already running. Use await() instead.')
    ;
});

it('allows sequential run() calls after completion', function () {
    $asyncOps = new AsyncOperations();
    $handler = new LoopExecutionHandler($asyncOps);

    $result1 = $handler->run(function () {
        return 'first result';
    });

    expect($result1)->toBe('first result');

    $result2 = $handler->run(function () {
        return 'second result';
    });

    expect($result2)->toBe('second result');
});

it('resets isRunning flag even when exception occurs in operation', function () {
    $asyncOps = new AsyncOperations();
    $handler = new LoopExecutionHandler($asyncOps);

    expect(fn () => $handler->run(function () {
        throw new Exception('Test exception');
    }))->toThrow(Exception::class, 'Test exception');

    $result = $handler->run(function () {
        return 'recovery successful';
    });

    expect($result)->toBe('recovery successful');
});
