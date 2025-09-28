<?php

use Hibla\Async\Timer;

use function Hibla\Promise\concurrentSettled;

use Hibla\Task\Task;

beforeEach(function () {
    resetEventLoop();
});

describe('Task Integration Tests', function () {
    it('integrates all components for complex async workflows', function () {
        $result = Task::run(function () {
            // Step 1: Concurrent operations with mixed types
            $step1Results = await(all([
                'fetch_user' => async(function () {
                    await(Timer::delay(0.02));

                    return ['id' => 1, 'name' => 'John'];
                }),
                'fetch_posts' => async(function () {
                    await(Timer::delay(0.03));

                    return [
                        ['id' => 1, 'title' => 'Post 1'],
                        ['id' => 2, 'title' => 'Post 2'],
                    ];
                }),
                'fetch_settings' => async(function () {
                    await(Timer::delay(0.01));

                    return ['theme' => 'dark', 'lang' => 'en'];
                }),
            ]));

            $operations = [];
            foreach ($step1Results['fetch_posts'] as $post) {
                $operations["process_post_{$post['id']}"] = async(function () use ($post) {
                    await(Timer::delay(0.015));

                    return array_merge($post, ['processed' => true]);
                });
            }

            // Step 2: Process results with controlled concurrency
            $step2Results = await(concurrent($operations, 2));

            return [
                'user' => $step1Results['fetch_user'],
                'settings' => $step1Results['fetch_settings'],
                'posts' => array_values($step2Results),
            ];
        });

        expect($result['user'])->toBe(['id' => 1, 'name' => 'John']);
        expect($result['settings'])->toBe(['theme' => 'dark', 'lang' => 'en']);
        expect($result['posts'])->toHaveCount(2);
        expect($result['posts'][0]['processed'])->toBeTrue();
        expect($result['posts'][1]['processed'])->toBeTrue();
    });

    it('handles error scenarios gracefully with settlement methods', function () {
        $operations = [
            'reliable_service' => function () {
                await(Timer::delay(0.01));

                return 'success';
            },
            'failing_service' => function () {
                await(Timer::delay(0.02));

                throw new RuntimeException('Service unavailable');
            },
            'slow_service' => function () {
                await(Timer::delay(0.05));

                return 'slow_success';
            },
        ];

        $results = Task::runAllSettled($operations);

        expect($results)->toHaveKeys(['reliable_service', 'failing_service', 'slow_service']);

        // Verify all results have proper settlement structure
        foreach ($results as $key => $result) {
            expect($result)->toHaveKey('status');
            expect($result['status'])->toBeIn(['fulfilled', 'rejected']);
        }

        expect($results['reliable_service']['status'])->toBe('fulfilled');
        expect($results['reliable_service']['value'])->toBe('success');

        expect($results['failing_service']['status'])->toBe('rejected');
        expect($results['failing_service']['reason'])->toBeInstanceOf(RuntimeException::class);
        expect($results['failing_service']['reason']->getMessage())->toBe('Service unavailable');

        expect($results['slow_service']['status'])->toBe('fulfilled');
        expect($results['slow_service']['value'])->toBe('slow_success');
    });

    it('benchmarks performance with large concurrent operations', function () {
        $operationCount = 50;
        $operations = [];

        for ($i = 0; $i < $operationCount; $i++) {
            $operations["op_$i"] = function () use ($i) {
                await(Timer::delay(0.01));

                return $i * 2;
            };
        }

        $start = microtime(true);
        $results = Task::runConcurrent($operations, 10);
        $elapsed = microtime(true) - $start;

        expect($results)->toHaveCount($operationCount);
        expect($results['op_0'])->toBe(0);
        expect($results['op_49'])->toBe(98);

        // With 10 concurrent operations, this should be much faster than sequential
        expect($elapsed)->toBeLessThan(0.5); // Should complete in reasonable time

        // Verify all keys are preserved
        for ($i = 0; $i < $operationCount; $i++) {
            expect($results)->toHaveKey("op_$i");
            expect($results["op_$i"])->toBe($i * 2);
        }
    });

    it('preserves key order in various scenarios', function () {
        // Test with mixed key types and different completion times
        $operations = [
            'z_last' => Timer::delay(0.001)->then(fn () => 'z'),
            'a_first' => Timer::delay(0.003)->then(fn () => 'a'),
            99 => Timer::delay(0.002)->then(fn () => 99),
            'middle' => Timer::delay(0.004)->then(fn () => 'm'),
            1 => Timer::delay(0.001)->then(fn () => 1),
        ];

        $results = Task::runAll($operations);

        // Keys should be preserved in original order
        expect(array_keys($results))->toBe(['z_last', 'a_first', 99, 'middle', 1]);
        expect($results['z_last'])->toBe('z');
        expect($results['a_first'])->toBe('a');
        expect($results[99])->toBe(99);
        expect($results['middle'])->toBe('m');
        expect($results[1])->toBe(1);
    });

    it('handles complex settlement scenarios with mixed success/failure', function () {
        $complexOperations = [
            'database_query' => function () {
                await(Timer::delay(0.02));

                return ['users' => [1, 2, 3]];
            },
            'api_call_1' => function () {
                await(Timer::delay(0.03));

                throw new Exception('API timeout');
            },
            'file_operation' => function () {
                await(Timer::delay(0.01));

                return 'file_content';
            },
            'api_call_2' => function () {
                await(Timer::delay(0.025));

                return ['status' => 'ok', 'data' => 'response'];
            },
            'failing_validation' => function () {
                await(Timer::delay(0.015));

                throw new RuntimeException('Validation failed');
            },
        ];

        $results = Task::runConcurrentSettled($complexOperations, 3);

        expect($results)->toHaveCount(5);
        expect($results)->toHaveKeys([
            'database_query',
            'api_call_1',
            'file_operation',
            'api_call_2',
            'failing_validation',
        ]);

        // Test successful operations
        expect($results['database_query']['status'])->toBe('fulfilled');
        expect($results['database_query']['value'])->toBe(['users' => [1, 2, 3]]);

        expect($results['file_operation']['status'])->toBe('fulfilled');
        expect($results['file_operation']['value'])->toBe('file_content');

        expect($results['api_call_2']['status'])->toBe('fulfilled');
        expect($results['api_call_2']['value'])->toBe(['status' => 'ok', 'data' => 'response']);

        // Test failed operations
        expect($results['api_call_1']['status'])->toBe('rejected');
        expect($results['api_call_1']['reason'])->toBeInstanceOf(Exception::class);
        expect($results['api_call_1']['reason']->getMessage())->toBe('API timeout');

        expect($results['failing_validation']['status'])->toBe('rejected');
        expect($results['failing_validation']['reason'])->toBeInstanceOf(RuntimeException::class);
        expect($results['failing_validation']['reason']->getMessage())->toBe('Validation failed');
    });

    it('demonstrates batch processing with settlement', function () {
        $batchOperations = [];

        // Create 12 operations in batches of 3
        for ($i = 1; $i <= 12; $i++) {
            if ($i % 4 === 0) {
                // Every 4th operation fails
                $batchOperations["batch_op_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));

                    throw new Exception("Batch operation $i failed");
                };
            } else {
                $batchOperations["batch_op_$i"] = function () use ($i) {
                    await(Timer::delay(0.01));

                    return "batch_result_$i";
                };
            }
        }

        $results = Task::runBatchSettled($batchOperations, 3, 2);

        expect($results)->toHaveCount(12);

        // Verify structure of all results
        $successCount = 0;
        $failureCount = 0;

        foreach ($results as $key => $result) {
            expect($result)->toHaveKey('status');

            if ($result['status'] === 'fulfilled') {
                $successCount++;
                expect($result)->toHaveKey('value');
                expect($result)->not->toHaveKey('reason');
            } else {
                $failureCount++;
                expect($result)->toHaveKey('reason');
                expect($result)->not->toHaveKey('value');
                expect($result['reason'])->toBeInstanceOf(Exception::class);
            }
        }

        // Should have 9 successes (12 - 3 failures at positions 4, 8, 12)
        expect($successCount)->toBe(9);
        expect($failureCount)->toBe(3);

        // Test specific results
        expect($results['batch_op_1']['status'])->toBe('fulfilled');
        expect($results['batch_op_1']['value'])->toBe('batch_result_1');

        expect($results['batch_op_4']['status'])->toBe('rejected');
        expect($results['batch_op_4']['reason']->getMessage())->toBe('Batch operation 4 failed');

        expect($results['batch_op_12']['status'])->toBe('rejected');
        expect($results['batch_op_12']['reason']->getMessage())->toBe('Batch operation 12 failed');
    });

    it('handles nested async operations with settlements', function () {
        $result = Task::run(function () {
            // First level - some operations
            $level1Results = await(allSettled([
                'step1' => async(function () {
                    await(Timer::delay(0.01));

                    return 'step1_complete';
                }),
                'step2' => async(function () {
                    await(Timer::delay(0.015));

                    throw new Exception('step2_failed');
                }),
            ]));

            $finalResult = ['level1' => $level1Results];

            // Second level - process results from first level
            if ($level1Results['step1']['status'] === 'fulfilled') {
                $level2Results = await(concurrentSettled([
                    'process_success' => async(function () use ($level1Results) {
                        await(Timer::delay(0.01));

                        return 'processed_' . $level1Results['step1']['value'];
                    }),
                    'handle_failure' => async(function () use ($level1Results) {
                        await(Timer::delay(0.01));
                        if ($level1Results['step2']['status'] === 'rejected') {
                            return 'handled_failure';
                        }

                        return 'no_failure_to_handle';
                    }),
                ], 2));

                $finalResult['level2'] = $level2Results;
            } else {
                $finalResult['level2'] = [
                    'process_success' => ['status' => 'rejected', 'reason' => 'No success to process'],
                ];
            }

            return $finalResult;
        });

        // Verify level 1 results
        expect($result['level1'])->toHaveKeys(['step1', 'step2']);
        expect($result['level1']['step1']['status'])->toBe('fulfilled');
        expect($result['level1']['step1']['value'])->toBe('step1_complete');
        expect($result['level1']['step2']['status'])->toBe('rejected');
        expect($result['level1']['step2']['reason'])->toBeInstanceOf(Exception::class);

        // Verify level 2 results
        expect($result['level2'])->toHaveKeys(['process_success', 'handle_failure']);
        expect($result['level2']['process_success']['status'])->toBe('fulfilled');
        expect($result['level2']['process_success']['value'])->toBe('processed_step1_complete');
        expect($result['level2']['handle_failure']['status'])->toBe('fulfilled');
        expect($result['level2']['handle_failure']['value'])->toBe('handled_failure');
    });

    it('verifies performance characteristics of settlement methods', function () {
        $operationCount = 50;
        $operations = [];

        for ($i = 0; $i < $operationCount; $i++) {
            if ($i === 10) {
                $operations["op_$i"] = fn () => throw new Exception("Operation $i failed");
            } else {
                $operations["op_$i"] = function () use ($i) {
                    Hibla\sleep(0.01);

                    return "result_$i";
                };
            }
        }

        $concurrentFailed = false;
        $concurrentTime = 0;

        try {
            $start = microtime(true);
            Task::runConcurrent($operations, 10);
        } catch (Exception $e) {
            $concurrentTime = microtime(true) - $start;
            $concurrentFailed = true;
        }

        $startSettled = microtime(true);
        $settledResults = Task::runConcurrentSettled($operations, 10);
        $settledTime = microtime(true) - $startSettled;

        expect($concurrentFailed)->toBeTrue();

        expect($settledResults)->toHaveCount($operationCount);

        // Count successful vs failed operations in settled results
        $successCount = 0;
        $failureCount = 0;

        foreach ($settledResults as $result) {
            if ($result['status'] === 'fulfilled') {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        expect($successCount)->toBe($operationCount - 1);
        expect($failureCount)->toBe(1);

        $timeDifference = $settledTime - $concurrentTime;
        $toleranceThreshold = $settledTime * 0.3;

        expect($timeDifference)->toBeGreaterThan(-$toleranceThreshold)
            ->and($concurrentTime)->toBeLessThan($settledTime + $toleranceThreshold)
        ;

        $expectedMinTime = 0.004;
        expect($settledTime)->toBeGreaterThan($expectedMinTime);
    });
});
