<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;
use function React\Promise\reject;
use function React\Promise\resolve;

class ParallelTest extends TestCase
{
    public function testParallelWithoutTasks(): void
    {
        /**
         * @var array<callable(): React\Promise\PromiseInterface<mixed>> $tasks
         */
        $tasks = array();

        $promise = React\Async\parallel($tasks);

        $promise->then($this->expectCallableOnceWith(array()));
    }

    public function testParallelWithoutTasksFromEmptyGeneratorResolvesWithEmptyArray(): void
    {
        $tasks = (function () {
            if (false) { // @phpstan-ignore-line
                yield fn () => resolve(null);
            }
        })();

        $promise = React\Async\parallel($tasks);

        $promise->then($this->expectCallableOnceWith([]));
    }

    public function testParallelWithTasks(): void
    {
        $tasks = array(
            function () {
                return new Promise(function ($resolve) {
                    Loop::addTimer(0.1, function () use ($resolve) {
                        $resolve('foo');
                    });
                });
            },
            function () {
                return new Promise(function ($resolve) {
                    Loop::addTimer(0.11, function () use ($resolve) {
                        $resolve('bar');
                    });
                });
            },
        );

        $promise = React\Async\parallel($tasks);

        $promise->then($this->expectCallableOnceWith(array('foo', 'bar')));

        $timer = new Timer($this);
        $timer->start();

        Loop::run();

        $timer->stop();
        $timer->assertInRange(0.1, 0.2);
    }

    public function testParallelWithTasksFromGeneratorResolvesWithArrayOfFulfillmentValues(): void
    {
        $tasks = (function () {
            yield function () {
                return new Promise(function ($resolve) {
                    Loop::addTimer(0.1, function () use ($resolve) {
                        $resolve('foo');
                    });
                });
            };
            yield function () {
                return new Promise(function ($resolve) {
                    Loop::addTimer(0.11, function () use ($resolve) {
                        $resolve('bar');
                    });
                });
            };
        })();

        $promise = React\Async\parallel($tasks);

        $promise->then($this->expectCallableOnceWith(array('foo', 'bar')));

        $timer = new Timer($this);
        $timer->start();

        Loop::run();

        $timer->stop();
        $timer->assertInRange(0.1, 0.2);
    }

    public function testParallelWithErrorReturnsPromiseRejectedWithExceptionFromTaskAndStopsCallingAdditionalTasks(): void
    {
        $called = 0;

        $tasks = array(
            function () use (&$called) {
                $called++;
                return new Promise(function ($resolve) {
                    $resolve('foo');
                });
            },
            function () use (&$called) {
                $called++;
                return new Promise(function () {
                    throw new \RuntimeException('whoops');
                });
            },
            function () use (&$called) {
                $called++;
                return new Promise(function ($resolve) {
                    $resolve('bar');
                });
            },
        );

        $promise = React\Async\parallel($tasks);

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('whoops')));

        $this->assertSame(2, $called);
    }

    public function testParallelWithErrorFromInfiniteGeneratorReturnsPromiseRejectedWithExceptionFromTaskAndStopsCallingAdditionalTasks(): void
    {
        $called = 0;

        $tasks = (function () use (&$called) {
            while (true) { // @phpstan-ignore-line
                yield function () use (&$called) {
                    return reject(new \RuntimeException('Rejected ' . ++$called));
                };
            }
        })();

        $promise = React\Async\parallel($tasks);

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Rejected 1')));

        $this->assertSame(1, $called);
    }

    public function testParallelWithErrorWillCancelPendingPromises(): void
    {
        $cancelled = 0;

        $tasks = array(
            function () use (&$cancelled) {
                return new Promise(function () { }, function () use (&$cancelled) {
                    $cancelled++;
                });
            },
            function () {
                return new Promise(function () {
                    throw new \RuntimeException('whoops');
                });
            },
            function () use (&$cancelled) {
                return new Promise(function () { }, function () use (&$cancelled) {
                    $cancelled++;
                });
            }
        );

        $promise = React\Async\parallel($tasks);

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('whoops')));

        $this->assertSame(1, $cancelled);
    }

    public function testParallelWillCancelPendingPromisesWhenCallingCancelOnResultingPromise(): void
    {
        $cancelled = 0;

        $tasks = array(
            function () use (&$cancelled) {
                return new Promise(function () { }, function () use (&$cancelled) {
                    $cancelled++;
                });
            },
            function () use (&$cancelled) {
                return new Promise(function () { }, function () use (&$cancelled) {
                    $cancelled++;
                });
            }
        );

        $promise = React\Async\parallel($tasks);
        assert(method_exists($promise, 'cancel'));
        $promise->cancel();

        $this->assertSame(2, $cancelled);
    }

    public function testParallelWithDelayedErrorReturnsPromiseRejectedWithExceptionFromTask(): void
    {
        $called = 0;

        $tasks = array(
            function () use (&$called) {
                $called++;
                return new Promise(function ($resolve) {
                    $resolve('foo');
                });
            },
            function () use (&$called) {
                $called++;
                return new Promise(function ($_, $reject) {
                    Loop::addTimer(0.001, function () use ($reject) {
                        $reject(new \RuntimeException('whoops'));
                    });
                });
            },
            function () use (&$called) {
                $called++;
                return new Promise(function ($resolve) {
                    $resolve('bar');
                });
            },
        );

        $promise = React\Async\parallel($tasks);

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('whoops')));

        Loop::run();

        $this->assertSame(3, $called);
    }
}
