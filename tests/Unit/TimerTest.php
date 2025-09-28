<?php

use Hibla\Async\Timer;

beforeEach(function () {
    resetEventLoop();
});

describe('Timer', function () {
    it('creates delay promises that resolve with null', function () {
        $start = microtime(true);

        run(function () use (&$result) {
            $result = await(Timer::delay(0.05));
        });

        $elapsed = microtime(true) - $start;

        expect($result)->toBeNull();
        expect($elapsed)->toBeGreaterThan(0.04);
        expect($elapsed)->toBeLessThan(0.1);
    });

    it('supports fractional seconds in delay', function () {
        $start = microtime(true);

        run(function () {
            await(Timer::delay(0.025));
        });

        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeGreaterThan(0.02);
        expect($elapsed)->toBeLessThan(0.05);
    });

    it('handles sleep functionality', function () {
        $start = microtime(true);

        run(function () use (&$result) {
            $result = Timer::sleep(0.03);
        });

        $elapsed = microtime(true) - $start;

        expect($result)->toBeNull();
        expect($elapsed)->toBeGreaterThan(0.025);
        expect($elapsed)->toBeLessThan(0.08);
    });

    it('can chain delay promises', function () {
        $start = microtime(true);

        run(function () use (&$results) {
            $results = [];

            await(Timer::delay(0.01));
            $results[] = 'first';

            await(Timer::delay(0.01));
            $results[] = 'second';

            await(Timer::delay(0.01));
            $results[] = 'third';
        });

        $elapsed = microtime(true) - $start;

        expect($results)->toBe(['first', 'second', 'third']);
        expect($elapsed)->toBeGreaterThan(0.025);
        expect($elapsed)->toBeLessThan(0.08);
    });
});
