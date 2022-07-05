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

    public function testCancelAsyncWillReturnRejectedPromiseWhenCancellingPendingPromiseRejects()
    {
        $promise = async(function () {
            await(new Promise(function () { }, function () {
                throw new \RuntimeException('Operation cancelled');
            }));
        })();

        $promise->cancel();

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Operation cancelled')));
    }

    public function testCancelAsyncWillReturnFulfilledPromiseWhenCancellingPendingPromiseRejectsInsideCatchThatReturnsValue()
    {
        $promise = async(function () {
            try {
                await(new Promise(function () { }, function () {
                    throw new \RuntimeException('Operation cancelled');
                }));
            } catch (\RuntimeException $e) {
                return 42;
            }
        })();

        $promise->cancel();

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCancelAsycWillReturnPendigPromiseWhenCancellingFirstPromiseRejectsInsideCatchThatAwaitsSecondPromise()
    {
        $promise = async(function () {
            try {
                await(new Promise(function () { }, function () {
                    throw new \RuntimeException('First operation cancelled');
                }));
            } catch (\RuntimeException $e) {
                await(new Promise(function () { }, function () {
                    throw new \RuntimeException('Second operation never cancelled');
                }));
            }
        })();

        $promise->cancel();

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testCancelAsyncWillCancelNestedAwait()
    {
        self::expectOutputString('abc');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operation cancelled');

        $promise = async(static function (): int {
            echo 'a';
            await(async(static function (): void {
                echo 'b';
                await(async(static function (): void {
                    echo 'c';
                    await(new Promise(function () { }, function () {
                        throw new \RuntimeException('Operation cancelled');
                    }));
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
}
