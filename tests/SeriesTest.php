<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;
use function React\Promise\reject;
use function React\Promise\resolve;

class SeriesTest extends TestCase
{
    public function testSeriesWithoutTasks(): void
    {
        /**
         * @var array<callable(): React\Promise\PromiseInterface<mixed>> $tasks
         */
        $tasks = array();

        $promise = React\Async\series($tasks);

        $promise->then($this->expectCallableOnceWith(array()));
    }

    public function testSeriesWithoutTasksFromEmptyGeneratorResolvesWithEmptyArray(): void
    {
        $tasks = (function () {
            if (false) { // @phpstan-ignore-line
                yield fn () => resolve(null);
            }
        })();

        $promise = React\Async\series($tasks);

        $promise->then($this->expectCallableOnceWith([]));
    }

    public function testSeriesWithTasks(): void
    {
        $tasks = array(
            function () {
                return new Promise(function ($resolve) {
                    Loop::addTimer(0.051, function () use ($resolve) {
                        $resolve('foo');
                    });
                });
            },
            function () {
                return new Promise(function ($resolve) {
                    Loop::addTimer(0.051, function () use ($resolve) {
                        $resolve('bar');
                    });
                });
            },
        );

        $promise = React\Async\series($tasks);

        $promise->then($this->expectCallableOnceWith(array('foo', 'bar')));

        $timer = new Timer($this);
        $timer->start();

        Loop::run();

        $timer->stop();
        $timer->assertInRange(0.10, 0.20);
    }

    public function testSeriesWithTasksFromGeneratorResolvesWithArrayOfFulfillmentValues(): void
    {
        $tasks = (function () {
            yield function () {
                return new Promise(function ($resolve) {
                    Loop::addTimer(0.051, function () use ($resolve) {
                        $resolve('foo');
                    });
                });
            };
            yield function () {
                return new Promise(function ($resolve) {
                    Loop::addTimer(0.051, function () use ($resolve) {
                        $resolve('bar');
                    });
                });
            };
        })();

        $promise = React\Async\series($tasks);

        $promise->then($this->expectCallableOnceWith(array('foo', 'bar')));

        $timer = new Timer($this);
        $timer->start();

        Loop::run();

        $timer->stop();
        $timer->assertInRange(0.10, 0.20);
    }

    public function testSeriesWithError(): void
    {
        $called = 0;

        $tasks = array(
            function () use (&$called) {
                $called++;
                return new Promise(function ($resolve) {
                    $resolve('foo');
                });
            },
            function () {
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

        $promise = React\Async\series($tasks);

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('whoops')));

        $this->assertSame(1, $called);
    }

    public function testSeriesWithErrorFromInfiniteGeneratorReturnsPromiseRejectedWithExceptionFromTaskAndStopsCallingAdditionalTasks(): void
    {
        $called = 0;

        $tasks = (function () use (&$called) {
            while (true) { // @phpstan-ignore-line
                yield function () use (&$called) {
                    return reject(new \RuntimeException('Rejected ' . ++$called));
                };
            }
        })();

        $promise = React\Async\series($tasks);

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Rejected 1')));

        $this->assertSame(1, $called);
    }

    public function testSeriesWithErrorFromInfiniteIteratorAggregateReturnsPromiseRejectedWithExceptionFromTaskAndStopsCallingAdditionalTasks(): void
    {
        $tasks = new class() implements \IteratorAggregate {
            public int $called = 0;

            /**
             * @return \Iterator<callable(): React\Promise\PromiseInterface<mixed>>
             */
            public function getIterator(): \Iterator
            {
                while (true) { // @phpstan-ignore-line
                    yield function () {
                        return reject(new \RuntimeException('Rejected ' . ++$this->called));
                    };
                }
            }
        };

        $promise = React\Async\series($tasks);

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Rejected 1')));

        $this->assertSame(1, $tasks->called);
    }

    public function testSeriesWillCancelFirstPendingPromiseWhenCallingCancelOnResultingPromise(): void
    {
        $cancelled = 0;

        $tasks = array(
            function () {
                return new Promise(function ($resolve) {
                    $resolve(null);
                });
            },
            function () use (&$cancelled) {
                return new Promise(function () { }, function () use (&$cancelled) {
                    $cancelled++;
                });
            }
        );

        $promise = React\Async\series($tasks);
        assert(method_exists($promise, 'cancel'));
        $promise->cancel();

        $this->assertSame(1, $cancelled);
    }
}
