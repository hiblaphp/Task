<?php

use Hibla\Async\Timer;
use Hibla\Task\Task;

beforeEach(function () {
    resetEventLoop();
});

describe('Promise Collection Features', function () {
    it('preserves string keys in promise collections', function () {
        $promises = [
            'first' => Timer::delay(0.01)->then(fn () => 'first_value'),
            'second' => Timer::delay(0.02)->then(fn () => 'second_value'),
            'third' => Timer::delay(0.005)->then(fn () => 'third_value'),
        ];

        $results = Task::runAll($promises);

        expect($results)->toEqual([
            'first' => 'first_value',
            'second' => 'second_value',
            'third' => 'third_value',
        ]);
        expect(array_keys($results))->toBe(['first', 'second', 'third']);
    });

    it('preserves numeric keys in promise collections', function () {
        $promises = [
            10 => Timer::delay(0.01)->then(fn () => 'ten'),
            20 => Timer::delay(0.01)->then(fn () => 'twenty'),
            5 => Timer::delay(0.01)->then(fn () => 'five'),
        ];

        $results = Task::runAll($promises);

        expect($results)->toEqual([
            10 => 'ten',
            20 => 'twenty',
            5 => 'five',
        ]);
        expect(array_keys($results))->toBe([10, 20, 5]);
    });

    it('preserves mixed key types in promise collections', function () {
        $promises = [
            'string_key' => Timer::delay(0.01)->then(fn () => 'string_result'),
            42 => Timer::delay(0.01)->then(fn () => 'numeric_result'),
            'another_string' => Timer::delay(0.01)->then(fn () => 'another_result'),
        ];

        $results = Task::runAll($promises);

        expect($results)->toEqual([
            'string_key' => 'string_result',
            42 => 'numeric_result',
            'another_string' => 'another_result',
        ]);
        expect(array_keys($results))->toBe(['string_key', 42, 'another_string']);
    });

    it('handles empty promise collections', function () {
        $results = Task::runAll([]);
        expect($results)->toBe([]);
    });

    it('handles single promise with key preservation', function () {
        $promises = ['only_key' => Timer::delay(0.01)->then(fn () => 'only_value')];

        $results = Task::runAll($promises);

        expect($results)->toBe(['only_key' => 'only_value']);
    });

    it('preserves keys in settled promise collections', function () {
        $promises = [
            'success_key' => Timer::delay(0.01)->then(fn () => 'success_value'),
            'failure_key' => Timer::delay(0.01)->then(function () {
                throw new Exception('promise_error');
            }),
            99 => Timer::delay(0.01)->then(fn () => 'numeric_success'),
        ];

        $results = Task::runAllSettled($promises);

        expect(array_keys($results))->toBe(['success_key', 'failure_key', 99]);

        expect($results['success_key']['status'])->toBe('fulfilled');
        expect($results['success_key']['value'])->toBe('success_value');

        expect($results['failure_key']['status'])->toBe('rejected');
        expect($results['failure_key']['reason'])->toBeInstanceOf(Exception::class);

        expect($results[99]['status'])->toBe('fulfilled');
        expect($results[99]['value'])->toBe('numeric_success');
    });
});
