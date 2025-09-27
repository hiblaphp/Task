<?php

use Hibla\Async\AsyncOperations;
use Hibla\Async\Timer;
use Hibla\Task\Handlers\ConcurrentExecutionHandler;
use Hibla\Task\Handlers\LoopExecutionHandler;

beforeEach(function () {
    resetEventLoop();
    $this->asyncOps = new AsyncOperations();
    $this->loopHandler = new LoopExecutionHandler($this->asyncOps);
    $this->handler = new ConcurrentExecutionHandler($this->asyncOps, $this->loopHandler);
});

describe('ConcurrentExecutionHandler', function () {
    it('executes all operations concurrently and preserves keys', function () {
        $operations = [
            'op1' =>  Timer::delay(0.01)->then(fn() => 'result1'),
            'op2' =>  Timer::delay(0.02)->then(fn() => 'result2'),
            42 =>  Timer::delay(0.005)->then(fn() => 'result42')
        ];

        $results = $this->handler->runAll($operations);

        expect($results)->toHaveKeys(['op1', 'op2', 42]);
        expect($results['op1'])->toBe('result1');
        expect($results['op2'])->toBe('result2');
        expect($results[42])->toBe('result42');
    });

    it('limits concurrency correctly', function () {
        $counter = 0;
        $maxConcurrent = 0;
        $currentConcurrent = 0;

        $operations = [];
        for ($i = 0; $i < 10; $i++) {
            $operations["task_$i"] = function () use (&$counter, &$maxConcurrent, &$currentConcurrent, $i) {
                $currentConcurrent++;
                $maxConcurrent = max($maxConcurrent, $currentConcurrent);

                await(Timer::delay(0.02));

                $currentConcurrent--;
                $counter++;
                return "result_$i";
            };
        }

        $results = $this->handler->runConcurrent($operations, 3);

        expect($results)->toHaveCount(10);
        expect($counter)->toBe(10);
        expect($maxConcurrent)->toBeLessThanOrEqual(3);
        expect($maxConcurrent)->toBeGreaterThan(1); // Ensure concurrency actually happened
    });

    it('processes batches sequentially', function () {
        $batchOrder = [];

        $operations = [];
        for ($i = 0; $i < 6; $i++) {
            $batchNum = intval($i / 2) + 1; // Batches of 2
            $operations["task_$i"] = function () use (&$batchOrder, $batchNum, $i) {
                await(Timer::delay(0.01));
                $batchOrder[] = $batchNum;
                return "result_$i";
            };
        }

        $results = $this->handler->runBatch($operations, 2);

        expect($results)->toHaveCount(6);

        // First two tasks should be batch 1, next two batch 2, etc.
        expect($batchOrder[0])->toBe(1);
        expect($batchOrder[1])->toBe(1);
        expect($batchOrder[2])->toBe(2);
        expect($batchOrder[3])->toBe(2);
    });

    it('handles settled operations with failures', function () {
        $operations = [
            'success' => function () {
                await(Timer::delay(0.01));
                return 'success_value';
            },
            'failure' => function () {
                await(Timer::delay(0.01));
                throw new Exception('deliberate_failure');
            },
            99 => function () {
                await(Timer::delay(0.01));
                return 'numeric_success';
            }
        ];

        $results = $this->handler->runAllSettled($operations);

        expect($results)->toHaveKeys(['success', 'failure', 99]);

        // Verify structure
        foreach ($results as $key => $result) {
            expect($result)->toHaveKey('status');
            expect($result['status'])->toBeIn(['fulfilled', 'rejected']);
        }

        // Test specific results
        expect($results['success']['status'])->toBe('fulfilled');
        expect($results['success']['value'])->toBe('success_value');

        expect($results['failure']['status'])->toBe('rejected');
        expect($results['failure']['reason'])->toBeInstanceOf(Exception::class);
        expect($results['failure']['reason']->getMessage())->toBe('deliberate_failure');

        expect($results[99]['status'])->toBe('fulfilled');
        expect($results[99]['value'])->toBe('numeric_success');
    });

    it('handles concurrent settled with mixed results', function () {
        $operations = [];
        for ($i = 1; $i <= 5; $i++) {
            if ($i % 2 === 0) {
                $operations["task_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));
                    throw new RuntimeException("Even task error $i");
                };
            } else {
                $operations["task_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));
                    return "odd_result_$i";
                };
            }
        }

        $results = $this->handler->runConcurrentSettled($operations, 2);

        expect($results)->toHaveCount(5);

        // Check odd tasks (successful)
        expect($results['task_1']['status'])->toBe('fulfilled');
        expect($results['task_1']['value'])->toBe('odd_result_1');

        expect($results['task_3']['status'])->toBe('fulfilled');
        expect($results['task_3']['value'])->toBe('odd_result_3');

        expect($results['task_5']['status'])->toBe('fulfilled');
        expect($results['task_5']['value'])->toBe('odd_result_5');

        // Check even tasks (failed)
        expect($results['task_2']['status'])->toBe('rejected');
        expect($results['task_2']['reason'])->toBeInstanceOf(RuntimeException::class);
        expect($results['task_2']['reason']->getMessage())->toBe('Even task error 2');

        expect($results['task_4']['status'])->toBe('rejected');
        expect($results['task_4']['reason'])->toBeInstanceOf(RuntimeException::class);
        expect($results['task_4']['reason']->getMessage())->toBe('Even task error 4');
    });

    it('handles batch settled with mixed results', function () {
        $operations = [];
        for ($i = 1; $i <= 4; $i++) {
            if ($i === 2) {
                $operations["batch_task_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));
                    throw new Exception("Batch failure $i");
                };
            } else {
                $operations["batch_task_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));
                    return "batch_success_$i";
                };
            }
        }

        $results = $this->handler->runBatchSettled($operations, 2);

        expect($results)->toHaveCount(4);
        expect($results)->toHaveKeys(['batch_task_1', 'batch_task_2', 'batch_task_3', 'batch_task_4']);

        // Verify all results have proper structure
        foreach ($results as $key => $result) {
            expect($result)->toHaveKey('status');
            if ($result['status'] === 'fulfilled') {
                expect($result)->toHaveKey('value');
                expect($result)->not->toHaveKey('reason');
            } else {
                expect($result)->toHaveKey('reason');
                expect($result)->not->toHaveKey('value');
            }
        }

        // Test specific results
        expect($results['batch_task_1']['status'])->toBe('fulfilled');
        expect($results['batch_task_1']['value'])->toBe('batch_success_1');

        expect($results['batch_task_2']['status'])->toBe('rejected');
        expect($results['batch_task_2']['reason'])->toBeInstanceOf(Exception::class);
        expect($results['batch_task_2']['reason']->getMessage())->toBe('Batch failure 2');

        expect($results['batch_task_3']['status'])->toBe('fulfilled');
        expect($results['batch_task_3']['value'])->toBe('batch_success_3');

        expect($results['batch_task_4']['status'])->toBe('fulfilled');
        expect($results['batch_task_4']['value'])->toBe('batch_success_4');
    });
});
