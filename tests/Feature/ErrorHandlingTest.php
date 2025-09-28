<?php

use Hibla\Async\Exceptions\TimeoutException;
use Hibla\Promise\Promise;
use Hibla\Task\Task;

beforeEach(function () {
    resetEventLoop();
});

describe('Task API Error Handling', function () {

    it('propagates exceptions from run() with callable', function () {
        expect(fn () => Task::run(function () {
            throw new RuntimeException('Test error from callable');
        }))->toThrow(RuntimeException::class, 'Test error from callable');
    });

    it('propagates exceptions from run() with promise', function () {
        $promise = new Promise(function ($resolve, $reject) {
            $reject(new InvalidArgumentException('Promise rejection error'));
        });

        expect(fn () => Task::run($promise))
            ->toThrow(InvalidArgumentException::class, 'Promise rejection error')
        ;
    });

    it('propagates exceptions from runStateful()', function () {
        expect(fn () => Task::runStateful(function () {
            throw new LogicException('Stateful run error');
        }))->toThrow(LogicException::class, 'Stateful run error');
    });

    it('handles errors in runAll() and fails fast', function () {
        $operations = [
            'success1' => fn () => 'result1',
            'error' => fn () => throw new Exception('Operation failed'),
            'success2' => fn () => 'result2', // This might not execute due to fail-fast
        ];

        expect(fn () => Task::runAll($operations))
            ->toThrow(Exception::class, 'Operation failed')
        ;
    });

    it('handles mixed success and failure in runAllSettled()', function () {
        $operations = [
            'success' => fn () => 'successful result',
            'error' => fn () => throw new RuntimeException('Failed operation'),
            'promise_success' => Promise::resolved('promise result'),
            'promise_error' => Promise::rejected(new InvalidArgumentException('Promise failed')),
        ];

        $results = Task::runAllSettled($operations);

        expect($results)->toHaveCount(4);

        expect($results['success'])->toBe([
            'status' => 'fulfilled',
            'value' => 'successful result',
        ]);

        expect($results['error']['status'])->toBe('rejected');
        expect($results['error']['reason'])->toBeInstanceOf(RuntimeException::class);
        expect($results['error']['reason']->getMessage())->toBe('Failed operation');

        expect($results['promise_success'])->toBe([
            'status' => 'fulfilled',
            'value' => 'promise result',
        ]);

        expect($results['promise_error']['status'])->toBe('rejected');
        expect($results['promise_error']['reason'])->toBeInstanceOf(InvalidArgumentException::class);
        expect($results['promise_error']['reason']->getMessage())->toBe('Promise failed');
    });

    it('handles errors in runConcurrent() with fail-fast behavior', function () {
        $operations = [
            fn () => 'success1',
            fn () => throw new Exception('Concurrent error'),
            fn () => 'success2',
        ];

        expect(fn () => Task::runConcurrent($operations, 2))
            ->toThrow(Exception::class, 'Concurrent error')
        ;
    });

    it('handles mixed results in runConcurrentSettled()', function () {
        $operations = [
            'op1' => fn () => 'success',
            'op2' => fn () => throw new RuntimeException('Failure'),
            'op3' => fn () => 'another success',
        ];

        $results = Task::runConcurrentSettled($operations, 2);

        expect($results)->toHaveCount(3);
        expect($results['op1']['status'])->toBe('fulfilled');
        expect($results['op2']['status'])->toBe('rejected');
        expect($results['op3']['status'])->toBe('fulfilled');
    });

    it('handles timeout errors in runWithTimeout()', function () {
        $slowPromise = new Promise(function ($resolve) {
            // This promise intentionally never resolves to test timeout
        });

        expect(fn () => Task::runWithTimeout($slowPromise, 0.1))
            ->toThrow(TimeoutException::class)
        ;
    });

    it('handles errors in runBatch()', function () {
        $operations = [
            fn () => 'batch1',
            fn () => throw new Exception('Batch error'),
            fn () => 'batch2',
        ];

        expect(fn () => Task::runBatch($operations, 2))
            ->toThrow(Exception::class, 'Batch error')
        ;
    });

    it('handles mixed results in runBatchSettled()', function () {
        $operations = [
            'batch1' => fn () => 'success1',
            'batch2' => fn () => throw new RuntimeException('Batch failure'),
            'batch3' => fn () => 'success2',
        ];

        $results = Task::runBatchSettled($operations, 2);

        expect($results)->toHaveCount(3);
        expect($results['batch1']['status'])->toBe('fulfilled');
        expect($results['batch2']['status'])->toBe('rejected');
        expect($results['batch3']['status'])->toBe('fulfilled');
    });

    it('preserves exception types and messages through the chain', function () {
        $customException = new class ('Custom error message') extends Exception {
            public function getCustomData(): string
            {
                return 'custom data';
            }
        };

        try {
            Task::run(function () use ($customException) {
                throw $customException;
            });

            expect(true)->toBeFalse('Expected exception was not thrown');
        } catch (Exception $e) {
            expect($e)->toBe($customException);
            expect($e->getMessage())->toBe('Custom error message');

            $customE = $customException;
            expect($customE->getCustomData())->toBe('custom data');

            expect(spl_object_id($e))->toBe(spl_object_id($customException));
        }
    });

    it('handles non-exception error values in promise rejections', function () {
        $promise = new Promise(function ($resolve, $reject) {
            $reject('String error message');
        });

        try {
            Task::run($promise);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            expect($e->getMessage())->toBe('String error message');
        }
    });

    it('prevents nested Task API calls and handles the error properly', function () {
        // This test verifies that nested Task API calls are properly prevented
        $operations = [
            'nested_attempt' => function () {
                // This should fail with "Cannot call run() while already running"
                return Task::runAllSettled([
                    'inner' => fn () => 'inner result',
                ]);
            },
            'normal_operation' => fn () => 'normal result',
        ];

        $results = Task::runAllSettled($operations);

        expect($results)->toHaveCount(2);

        expect($results['nested_attempt']['status'])->toBe('rejected');
        expect($results['nested_attempt']['reason'])->toBeInstanceOf(RuntimeException::class);
        expect($results['nested_attempt']['reason']->getMessage())
            ->toBe('Cannot call run() while already running. Use await() instead.')
        ;

        expect($results['normal_operation']['status'])->toBe('fulfilled');
        expect($results['normal_operation']['value'])->toBe('normal result');
    });

    it('handles promise-based nested operations correctly', function () {
        $operations = [
            'complex_async' => new Promise(function ($resolve, $reject) {
                $resolve([
                    'nested_success' => 'nested result',
                    'nested_data' => ['key' => 'value'],
                ]);
            }),
            'simple_operation' => fn () => 'simple result',
        ];

        $results = Task::runAllSettled($operations);

        expect($results)->toHaveCount(2);
        expect($results['complex_async']['status'])->toBe('fulfilled');
        expect($results['simple_operation']['status'])->toBe('fulfilled');

        $complexResult = $results['complex_async']['value'];
        expect($complexResult['nested_success'])->toBe('nested result');
        expect($complexResult['nested_data']['key'])->toBe('value');
    });

    it('ensures proper cleanup after errors', function () {
        // Run an operation that fails
        expect(fn () => Task::run(function () {
            throw new Exception('Cleanup test error');
        }))->toThrow(Exception::class);

        // Verify that subsequent operations work fine (cleanup was proper)
        $result = Task::run(function () {
            return 'cleanup successful';
        });

        expect($result)->toBe('cleanup successful');
    });
});
