<?php

use Hibla\Async\AsyncOperations;
use Hibla\Async\Timer;
use Hibla\Task\Handlers\LoopExecutionHandler;

beforeEach(function () {
    resetEventLoop();
    $this->asyncOps = new AsyncOperations();
    $this->handler = new LoopExecutionHandler($this->asyncOps);
});

describe('LoopExecutionHandler', function () {
    it('runs callable operations and manages event loop', function () {
        $result = $this->handler->run(function () {
            await(Timer::delay(0.01));

            return 'test_result';
        });

        expect($result)->toBe('test_result');
    });

    it('runs promise operations directly', function () {
        $promise = Timer::delay(0.01)->then(fn () => 'promise_result');

        $result = $this->handler->run($promise);

        expect($result)->toBe('promise_result');
    });

    it('creates promises from operations correctly', function () {
        // Test callable
        $callablePromise = $this->handler->createPromiseFromOperation(function () {
            await(Timer::delay(0.01));

            return 'callable_result';
        });

        expect($callablePromise)->toBeInstanceOf(Hibla\Promise\Interfaces\PromiseInterface::class);

        // Test existing promise
        $existingPromise = Timer::delay(0.01)->then(fn () => 'existing_result');
        $wrappedPromise = $this->handler->createPromiseFromOperation($existingPromise);

        expect($wrappedPromise)->toBe($existingPromise);
    });

    it('handles exceptions in operations', function () {
        expect(function () {
            $this->handler->run(function () {
                await(Timer::delay(0.01));

                throw new RuntimeException('test_exception');
            });
        })->toThrow(RuntimeException::class, 'test_exception');
    });

    it('handles promise rejections', function () {
        $promise = Timer::delay(0.01)->then(function () {
            throw new Exception('promise_rejection');
        });

        expect(function () use ($promise) {
            $this->handler->run($promise);
        })->toThrow(Exception::class, 'promise_rejection');
    });
});
