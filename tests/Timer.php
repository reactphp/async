<?php

namespace React\Tests\Async;

class Timer
{
    private $testCase;
    private $start;
    private $stop;

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    public function start()
    {
        $this->start = microtime(true);
    }

    public function stop()
    {
        $this->stop = microtime(true);
    }

    public function getInterval()
    {
        return $this->stop - $this->start;
    }

    public function assertLessThan($milliseconds)
    {
        $this->testCase->assertLessThan($milliseconds, $this->getInterval());
    }

    public function assertGreaterThan($milliseconds)
    {
        $this->testCase->assertGreaterThan($milliseconds, $this->getInterval());
    }

    public function assertInRange($minMs, $maxMs)
    {
        $this->assertGreaterThan($minMs);
        $this->assertLessThan($maxMs);
    }
}
