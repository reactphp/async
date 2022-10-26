<?php

namespace React\Tests\Async;

class Timer
{
    /** @var TestCase */
    private $testCase;

    /** @var float */
    private $start;

    /** @var float */
    private $stop;

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    public function start(): void
    {
        $this->start = microtime(true);
    }

    public function stop(): void
    {
        $this->stop = microtime(true);
    }

    public function getInterval(): float
    {
        return $this->stop - $this->start;
    }

    public function assertLessThan(float $milliseconds): void
    {
        $this->testCase->assertLessThan($milliseconds, $this->getInterval());
    }

    public function assertGreaterThan(float $milliseconds): void
    {
        $this->testCase->assertGreaterThan($milliseconds, $this->getInterval());
    }

    public function assertInRange(float $minMs, float $maxMs): void
    {
        $this->assertGreaterThan($minMs);
        $this->assertLessThan($maxMs);
    }
}
