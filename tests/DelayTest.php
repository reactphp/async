<?php

namespace React\Tests\Async;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use function React\Async\delay;

class DelayTest extends TestCase
{
    public function testDelayBlocksForGivenPeriod()
    {
        $time = microtime(true);
        delay(0.02);
        $time = microtime(true) - $time;

        $this->assertGreaterThan(0.01, $time);
        $this->assertLessThan(0.03, $time);
    }

    public function testDelaySmallPeriodBlocksForCloseToZeroSeconds()
    {
        $time = microtime(true);
        delay(0.000001);
        $time = microtime(true) - $time;

        $this->assertLessThan(0.01, $time);
    }

    public function testDelayNegativePeriodBlocksForCloseToZeroSeconds()
    {
        $time = microtime(true);
        delay(-1);
        $time = microtime(true) - $time;

        $this->assertLessThan(0.01, $time);
    }

    public function testAwaitAsyncDelayBlocksForGivenPeriod()
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

    public function testAwaitAsyncDelayCancelledImmediatelyStopsTimerAndBlocksForCloseToZeroSeconds()
    {
        $promise = async(function () {
            delay(1.0);
        })();
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

    public function testAwaitAsyncDelayCancelledAfterSmallPeriodStopsTimerAndBlocksUntilCancelled()
    {
        $promise = async(function () {
            delay(1.0);
        })();
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
