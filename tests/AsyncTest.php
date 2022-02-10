<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class AsyncTest extends TestCase
{
    public function testAsyncReturnsPromiseThatFulfillsWithValueWhenCallbackReturnsValue()
    {
        $promise = async(function () {
            return 42;
        })();

        $value = null;
        $promise->then(function ($v) use (&$value) {
            $value = $v;
        });

        $this->assertEquals(42, $value);
    }

    public function testAsyncReturnsPromiseThatFulfillsWithValueWhenCallbackReturnsPromiseThatFulfillsWithValue()
    {
        $promise = async(function () {
            return resolve(42);
        })();

        $value = null;
        $promise->then(function ($v) use (&$value) {
            $value = $v;
        });

        $this->assertEquals(42, $value);
    }

    public function testAsyncReturnsPromiseThatRejectsWithExceptionWhenCallbackThrows()
    {
        $promise = async(function () {
            throw new \RuntimeException('Foo', 42);
        })();

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        assert($exception instanceof \RuntimeException);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Foo', $exception->getMessage());
        $this->assertEquals(42, $exception->getCode());
    }

    public function testAsyncReturnsPromiseThatRejectsWithExceptionWhenCallbackReturnsPromiseThatRejectsWithException()
    {
        $promise = async(function () {
            return reject(new \RuntimeException('Foo', 42));
        })();

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        assert($exception instanceof \RuntimeException);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Foo', $exception->getMessage());
        $this->assertEquals(42, $exception->getCode());
    }

    public function testAsyncReturnsPendingPromiseWhenCallbackReturnsPendingPromise()
    {
        $promise = async(function () {
            return new Promise(function () { });
        })();

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testAsyncReturnsPromiseThatFulfillsWithValueWhenCallbackReturnsAfterAwaitingPromise()
    {
        $promise = async(function () {
            $promise = new Promise(function ($resolve) {
                Loop::addTimer(0.001, fn () => $resolve(42));
            });

            return await($promise);
        })();

        $value = await($promise);

        $this->assertEquals(42, $value);
    }

    public function testAsyncReturnsPromiseThatFulfillsWithValueWhenCallbackReturnsAfterAwaitingTwoConcurrentPromises()
    {
        $promise1 = async(function () {
            $promise = new Promise(function ($resolve) {
                Loop::addTimer(0.11, fn () => $resolve(21));
            });

            return await($promise);
        })();

        $promise2 = async(function (int $theAnswerToLifeTheUniverseAndEverything): int {
            $promise = new Promise(function ($resolve) use ($theAnswerToLifeTheUniverseAndEverything): void {
                Loop::addTimer(0.11, fn () => $resolve($theAnswerToLifeTheUniverseAndEverything));
            });

            return await($promise);
        })(42);

        $time = microtime(true);
        $values = await(all([$promise1, $promise2]));
        $time = microtime(true) - $time;

        $this->assertEquals([21, 42], $values);
        $this->assertGreaterThan(0.1, $time);
        $this->assertLessThan(0.12, $time);
    }
}
