<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

class AsyncTest extends TestCase
{
    public function testAsyncReturnsPendingPromise()
    {
        $promise = async(function () {
            return 42;
        })();

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testAsyncReturnsPromiseThatFulfillsWithValueWhenCallbackReturns()
    {
        $promise = async(function () {
            return 42;
        })();

        $value = await($promise);

        $this->assertEquals(42, $value);
    }

    public function testAsyncReturnsPromiseThatRejectsWithExceptionWhenCallbackThrows()
    {
        $promise = async(function () {
            throw new \RuntimeException('Foo', 42);
        })();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Foo');
        $this->expectExceptionCode(42);
        await($promise);
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
