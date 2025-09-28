<?php

use Hibla\Async\Timer;
use Hibla\Task\Task;

beforeEach(function () {
    resetEventLoop();
});

describe('Task API Feature Tests', function () {
    it('runs a single async operation with run()', function () {
        $result = Task::run(function () {
            await(Timer::delay(0.01));

            return 'completed';
        });

        expect($result)->toBe('completed');
    });

    it('runs multiple operations concurrently with runAll()', function () {
        $operations = [
            'fast' => function () {
                await(Timer::delay(0.01));

                return 'fast_result';
            },
            'slow' => function () {
                await(Timer::delay(0.02));

                return 'slow_result';
            },
            2 => function () {
                await(Timer::delay(0.005));

                return 'numeric_key_result';
            },
        ];

        $results = Task::runAll($operations);

        expect($results)->toHaveKeys(['fast', 'slow', 2]);
        expect($results['fast'])->toBe('fast_result');
        expect($results['slow'])->toBe('slow_result');
        expect($results[2])->toBe('numeric_key_result');
    });

    it('runs operations with concurrency control using runConcurrent()', function () {
        $executionOrder = [];

        $operations = [];
        for ($i = 1; $i <= 5; $i++) {
            $operations["task_$i"] = function () use ($i, &$executionOrder) {
                await(Timer::delay(0.01));
                $executionOrder[] = $i;

                return "result_$i";
            };
        }

        $results = Task::runConcurrent($operations, 2);

        expect($results)->toHaveCount(5);
        expect($results)->toHaveKeys(['task_1', 'task_2', 'task_3', 'task_4', 'task_5']);
        expect($results['task_1'])->toBe('result_1');
        expect($results['task_5'])->toBe('result_5');
    });

    it('handles mixed promise and callable operations', function () {
        $operations = [
            'promise' => Timer::delay(0.01)->then(fn () => 'promise_result'),
            'callable' => function () {
                await(Timer::delay(0.01));

                return 'callable_result';
            },
        ];

        $results = Task::runAll($operations);

        expect($results)->toBe([
            'promise' => 'promise_result',
            'callable' => 'callable_result',
        ]);
    });

    it('preserves array keys in all operations', function () {
        $operations = [
            'string_key' => fn () => 'string_value',
            42 => fn () => 'numeric_value',
            'another' => fn () => 'another_value',
        ];

        $results = Task::runAll($operations);

        expect($results)->toEqual([
            'string_key' => 'string_value',
            42 => 'numeric_value',
            'another' => 'another_value',
        ]);
        expect(array_keys($results))->toBe(['string_key', 42, 'another']);
    });
});

describe('Task Settlement Methods', function () {
    it('handles all settled operations including failures', function () {
        $operations = [
            'success1' => function () {
                await(Timer::delay(0.01));

                return 'success_value';
            },
            'failure' => function () {
                await(Timer::delay(0.01));

                throw new Exception('test_error');
            },
            'success2' => function () {
                await(Timer::delay(0.01));

                return 'another_success';
            },
        ];

        $results = Task::runAllSettled($operations);

        expect($results)->toHaveCount(3);
        expect($results)->toHaveKeys(['success1', 'failure', 'success2']);

        foreach ($results as $key => $result) {
            expect($result)->toHaveKey('status');
            expect($result['status'])->toBeIn(['fulfilled', 'rejected']);

            if ($result['status'] === 'fulfilled') {
                expect($result)->toHaveKey('value');
                expect($result)->not->toHaveKey('reason');
            } else {
                expect($result)->toHaveKey('reason');
                expect($result)->not->toHaveKey('value');
            }
        }

        // Test specific results
        expect($results['success1']['status'])->toBe('fulfilled');
        expect($results['success1']['value'])->toBe('success_value');

        expect($results['failure']['status'])->toBe('rejected');
        expect($results['failure']['reason'])->toBeInstanceOf(Exception::class);
        expect($results['failure']['reason']->getMessage())->toBe('test_error');

        expect($results['success2']['status'])->toBe('fulfilled');
        expect($results['success2']['value'])->toBe('another_success');
    });

    it('handles concurrent settled with concurrency limit', function () {
        $operations = [];
        for ($i = 1; $i <= 5; $i++) {
            if ($i === 3) {
                $operations["task_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));

                    throw new RuntimeException("Error in task $i");
                };
            } else {
                $operations["task_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));

                    return "result_$i";
                };
            }
        }

        $results = Task::runConcurrentSettled($operations, 2);

        expect($results)->toHaveCount(5);
        expect($results)->toHaveKeys(['task_1', 'task_2', 'task_3', 'task_4', 'task_5']);

        foreach ($results as $key => $result) {
            expect($result)->toHaveKey('status');
            expect($result['status'])->toBeIn(['fulfilled', 'rejected']);
        }

        expect($results['task_1']['status'])->toBe('fulfilled');
        expect($results['task_1']['value'])->toBe('result_1');

        expect($results['task_3']['status'])->toBe('rejected');
        expect($results['task_3']['reason'])->toBeInstanceOf(RuntimeException::class);
        expect($results['task_3']['reason']->getMessage())->toBe('Error in task 3');

        expect($results['task_5']['status'])->toBe('fulfilled');
        expect($results['task_5']['value'])->toBe('result_5');
    });

    it('handles batch settled operations', function () {
        $operations = [];
        for ($i = 1; $i <= 6; $i++) {
            if ($i === 2 || $i === 5) {
                $operations["task_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));

                    throw new RuntimeException("Batch error $i");
                };
            } else {
                $operations["task_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));

                    return "batch_result_$i";
                };
            }
        }

        $results = Task::runBatchSettled($operations, 2);

        expect($results)->toHaveCount(6);

        // Verify all have proper settlement structure
        foreach ($results as $key => $result) {
            expect($result)->toHaveKey('status');
            expect($result['status'])->toBeIn(['fulfilled', 'rejected']);

            if ($result['status'] === 'fulfilled') {
                expect($result)->toHaveKey('value');
                expect($result)->not->toHaveKey('reason');
            } else {
                expect($result)->toHaveKey('reason');
                expect($result)->not->toHaveKey('value');
                expect($result['reason'])->toBeInstanceOf(RuntimeException::class);
            }
        }

        expect($results['task_1']['status'])->toBe('fulfilled');
        expect($results['task_1']['value'])->toBe('batch_result_1');

        expect($results['task_6']['status'])->toBe('fulfilled');
        expect($results['task_6']['value'])->toBe('batch_result_6');

        expect($results['task_2']['status'])->toBe('rejected');
        expect($results['task_2']['reason']->getMessage())->toBe('Batch error 2');

        expect($results['task_5']['status'])->toBe('rejected');
        expect($results['task_5']['reason']->getMessage())->toBe('Batch error 5');
    });

    it('preserves keys in settled operations', function () {
        $operations = [
            'alpha' => function () {
                await(Timer::delay(0.01));

                return 'alpha_value';
            },
            99 => function () {
                await(Timer::delay(0.01));

                throw new Exception('numeric_error');
            },
            'beta' => function () {
                await(Timer::delay(0.01));

                return 'beta_value';
            },
        ];

        $results = Task::runAllSettled($operations);

        expect(array_keys($results))->toBe(['alpha', 99, 'beta']);

        expect($results['alpha']['status'])->toBe('fulfilled');
        expect($results['alpha']['value'])->toBe('alpha_value');

        expect($results[99]['status'])->toBe('rejected');
        expect($results[99]['reason']->getMessage())->toBe('numeric_error');

        expect($results['beta']['status'])->toBe('fulfilled');
        expect($results['beta']['value'])->toBe('beta_value');
    });

    it('handles empty arrays in settled operations', function () {
        $results = Task::runAllSettled([]);
        expect($results)->toBe([]);

        $results = Task::runConcurrentSettled([], 5);
        expect($results)->toBe([]);

        $results = Task::runBatchSettled([], 3);
        expect($results)->toBe([]);
    });
});
