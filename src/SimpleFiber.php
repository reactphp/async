<?php

namespace React\Async;

use React\EventLoop\Loop;

/**
 * @internal
 */
final class SimpleFiber implements FiberInterface
{
    private static ?\Fiber $scheduler = null;
    private static ?\Closure $suspend = null;
    private ?\Fiber $fiber = null;

    public function __construct()
    {
        $this->fiber = \Fiber::getCurrent();
    }

    public function resume(mixed $value): void
    {
        if ($this->fiber === null) {
            $suspend = static fn() => $value;
            if (\Fiber::getCurrent() !== self::$scheduler) {
                self::$suspend = $suspend;
            } else {
                \Fiber::suspend($suspend);
            }
            return;
        }

        $this->fiber->resume($value);

        if (self::$suspend) {
            $suspend = self::$suspend;
            self::$suspend = null;

            \Fiber::suspend($suspend);
        }
    }

    public function throw(\Throwable $throwable): void
    {
        if ($this->fiber === null) {
            $suspend = static fn() => throw $throwable;
            if (\Fiber::getCurrent() !== self::$scheduler) {
                self::$suspend = $suspend;
            } else {
                \Fiber::suspend($suspend);
            }
            return;
        }

        $this->fiber->throw($throwable);

        if (self::$suspend) {
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
                    if (self::$scheduler->isSuspended()) {
                        self::$scheduler->resume();
                    }
                });
            }

            return (self::$scheduler->isStarted() ? self::$scheduler->resume() : self::$scheduler->start())();
        }

        return \Fiber::suspend();
    }
}
