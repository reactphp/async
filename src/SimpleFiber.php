<?php

namespace React\Async;

use React\EventLoop\Loop;

/**
 * @internal
 */
final class SimpleFiber implements FiberInterface
{
    /** @var ?\Fiber<void,void,void,callable(): mixed> */
    private static ?\Fiber $scheduler = null;

    private static ?\Closure $suspend = null;

    /** @var ?\Fiber<mixed,mixed,mixed,mixed> */
    private ?\Fiber $fiber = null;

    public function __construct()
    {
        $this->fiber = \Fiber::getCurrent();
    }

    public function resume(mixed $value): void
    {
        if ($this->fiber !== null) {
            $this->fiber->resume($value);
        } else {
            self::$suspend = static fn() => $value;
        }

        if (self::$suspend !== null && \Fiber::getCurrent() === self::$scheduler) {
            $suspend = self::$suspend;
            self::$suspend = null;

            \Fiber::suspend($suspend);
        }
    }

    public function throw(\Throwable $throwable): void
    {
        if ($this->fiber !== null) {
            $this->fiber->throw($throwable);
        } else {
            self::$suspend = static fn() => throw $throwable;
        }

        if (self::$suspend !== null && \Fiber::getCurrent() === self::$scheduler) {
            $suspend = self::$suspend;
            self::$suspend = null;

            \Fiber::suspend($suspend);
        }
    }

    public function suspend(): mixed
    {
        if ($this->fiber === null) {
            if (self::$scheduler === null || self::$scheduler->isTerminated()) {
                self::$scheduler = new \Fiber(static fn() => Loop::run());
                // Run event loop to completion on shutdown.
                \register_shutdown_function(static function (): void {
                    assert(self::$scheduler instanceof \Fiber);
                    if (self::$scheduler->isSuspended()) {
                        self::$scheduler->resume();
                    }
                });
            }

            $ret = (self::$scheduler->isStarted() ? self::$scheduler->resume() : self::$scheduler->start());
            assert(\is_callable($ret));

            return $ret();
        }

        return \Fiber::suspend();
    }
}
