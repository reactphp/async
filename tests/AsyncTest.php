<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\Promise;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;
use function React\Promise\Timer\sleep;

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

    public function testAsyncWithAwaitReturnsReturnsPromiseFulfilledWithValueImmediatelyWhenPromiseIsFulfilled()
    {
        $deferred = new Deferred();

        $promise = async(function () use ($deferred) {
            return await($deferred->promise());
        })();

        $return = null;
        $promise->then(function ($value) use (&$return) {
            $return = $value;
        });

        $this->assertNull($return);

        $deferred->resolve(42);

        $this->assertEquals(42, $return);
    }

    public function testAsyncWithAwaitReturnsPromiseRejectedWithExceptionImmediatelyWhenPromiseIsRejected()
    {
        $deferred = new Deferred();

        $promise = async(function () use ($deferred) {
            return await($deferred->promise());
        })();

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        $this->assertNull($exception);

        $deferred->reject(new \RuntimeException('Test', 42));

        $this->assertInstanceof(\RuntimeException::class, $exception);
        assert($exception instanceof \RuntimeException);
        $this->assertEquals('Test', $exception->getMessage());
        $this->assertEquals(42, $exception->getCode());
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

    public function testAsyncReturnsPromiseThatRejectsWithExceptionWhenCallbackThrowsAfterAwaitingPromise()
    {
        $promise = async(function () {
            $promise = new Promise(function ($_, $reject) {
                Loop::addTimer(0.001, fn () => $reject(new \RuntimeException('Foo', 42)));
            });

            return await($promise);
        })();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Foo');
        $this->expectExceptionCode(42);
        await($promise);
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

    public function testCancel()
    {
        self::expectOutputString('a');
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Timer cancelled');

        $promise = async(static function (): int {
            echo 'a';
            await(sleep(2));
            echo 'b';

            return time();
        })();

        $promise->cancel();
        await($promise);
    }

    public function testCancelTryCatch()
    {
        self::expectOutputString('ab');

        $promise = async(static function (): int {
            echo 'a';
            try {
                await(sleep(2));
            } catch (\Throwable) {
                // No-Op
            }
            echo 'b';

            return time();
        })();

        $promise->cancel();
        await($promise);
    }

    public function testNestedCancel()
    {
        self::expectOutputString('abc');
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Timer cancelled');

        $promise = async(static function (): int {
            echo 'a';
            await(async(static function(): void {
                echo 'b';
                await(async(static function(): void {
                    echo 'c';
                    await(sleep(2));
                    echo 'd';
                })());
                echo 'e';
            })());
            echo 'f';

            return time();
        })();

        $promise->cancel();
        await($promise);
    }

    public function testCancelFiberThatCatchesExceptions()
    {
        self::expectOutputString('ab');
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Timer cancelled');

        $promise = async(static function (): int {
            echo 'a';
            try {
                await(sleep(2));
            } catch (\Throwable) {
                // No-Op
            }
            echo 'b';
            await(sleep(0.1));
            echo 'c';

            return time();
        })();

        $promise->cancel();
        await($promise);
    }

    public function testNotAwaitedPromiseWillNotBeCanceled()
    {
        self::expectOutputString('acb');

        async(static function (): int {
            echo 'a';
            sleep(0.001)->then(static function (): void {
                echo 'b';
            });
            echo 'c';

            return time();
        })()->cancel();
        Loop::run();
    }
}
