<?php

namespace React\Tests\Async;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use function React\Async\delay;

class DelayTest extends TestCase
{
    public function testDelayBlocksForGivenPeriod(): void
    {
        $time = microtime(true);
        delay(0.02);
        $time = microtime(true) - $time;

        $this->assertEqualsWithDelta(0.02, $time, 0.01);
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

    public function testDelayRunsOtherEventsWhileWaiting(): void
    {
        $buffer = 'a';
        Loop::addTimer(0.001, function () use (&$buffer) {
            $buffer .= 'c';
        });
        $buffer .= 'b';
        delay(0.002);
        $buffer .= 'd';

        $this->assertEquals('abcd', $buffer);
    }
}
