<?php

namespace React\Tests\Async;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use function React\Async\delay;

class DelayTest extends TestCase
{
    public function testDelayBlocksForGivenPeriod(): void
    {
        $time = microtime(true);
        delay(0.02);
        $time = microtime(true) - $time;

        $this->assertGreaterThan(0.01, $time);
        $this->assertLessThan(0.03, $time);
    }

    public function testDelaySmallPeriodBlocksForCloseToZeroSeconds(): void
    {
        $time = microtime(true);
        delay(0.000001);
        $time = microtime(true) - $time;

        $this->assertLessThan(0.01, $time);
    }

    public function testDelayNegativePeriodBlocksForCloseToZeroSeconds(): void
    {
        $time = microtime(true);
        delay(-1);
        $time = microtime(true) - $time;

        $this->assertLessThan(0.01, $time);
    }

    public function testAwaitAsyncDelayBlocksForGivenPeriod(): void
    {
        $promise = async(function () {
            delay(0.02);
        })();

        $time = microtime(true);
        await($promise);
        $time = microtime(true) - $time;

        $this->assertGreaterThan(0.01, $time);
        $this->assertLessThan(0.03, $time);
    }

    public function testAwaitAsyncDelayCancelledImmediatelyStopsTimerAndBlocksForCloseToZeroSeconds(): void
    {
        $promise = async(function () {
            delay(1.0);
        })();

        assert(method_exists($promise, 'cancel'));
        $promise->cancel();

        $time = microtime(true);
        try {
            await($promise);
        } catch (\RuntimeException $e) {
            $this->assertEquals('Delay cancelled', $e->getMessage());
        }
        $time = microtime(true) - $time;

        $this->assertLessThan(0.03, $time);
    }

    public function testAwaitAsyncDelayCancelledAfterSmallPeriodStopsTimerAndBlocksUntilCancelled(): void
    {
        $promise = async(function () {
            delay(1.0);
        })();

        assert(method_exists($promise, 'cancel'));
        Loop::addTimer(0.02, fn() => $promise->cancel());

        $time = microtime(true);
        try {
            await($promise);
        } catch (\RuntimeException $e) {
            $this->assertEquals('Delay cancelled', $e->getMessage());
        }
        $time = microtime(true) - $time;

        $this->assertGreaterThan(0.01, $time);
        $this->assertLessThan(0.03, $time);
    }
}
