<?php

namespace React\Tests\Async;

use function React\Async\coroutine;
use function React\Promise\reject;
use function React\Promise\resolve;

class CoroutineTest extends TestCase
{
    public function testCoroutineReturnsFulfilledPromiseIfFunctionReturnsWithoutGenerator()
    {
        $promise = coroutine(function () {
            return 42;
        });

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCoroutineReturnsFulfilledPromiseIfFunctionReturnsImmediately()
    {
        $promise = coroutine(function () {
            if (false) {
                yield;
            }
            return 42;
        });

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCoroutineReturnsFulfilledPromiseIfFunctionReturnsAfterYieldingPromise()
    {
        $promise = coroutine(function () {
            $value = yield resolve(42);
            return $value;
        });

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionThrowsWithoutGenerator()
    {
        $promise = coroutine(function () {
            throw new \RuntimeException('Foo');
        });

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Foo')));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionThrowsImmediately()
    {
        $promise = coroutine(function () {
            if (false) {
                yield;
            }
            throw new \RuntimeException('Foo');
        });

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Foo')));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionThrowsAfterYieldingPromise()
    {
        $promise = coroutine(function () {
            $reason = yield resolve('Foo');
            throw new \RuntimeException($reason);
        });

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Foo')));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionThrowsAfterYieldingRejectedPromise()
    {
        $promise = coroutine(function () {
            try {
                yield reject(new \OverflowException('Foo'));
            } catch (\OverflowException $e) {
                throw new \RuntimeException($e->getMessage());
            }
        });

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('Foo')));
    }

    public function testCoroutineReturnsFulfilledPromiseIfFunctionReturnsAfterYieldingRejectedPromise()
    {
        $promise = coroutine(function () {
            try {
                yield reject(new \OverflowException('Foo', 42));
            } catch (\OverflowException $e) {
                return $e->getCode();
            }
        });

        $promise->then($this->expectCallableOnceWith(42));
    }

    public function testCoroutineReturnsRejectedPromiseIfFunctionYieldsInvalidValue()
    {
        $promise = coroutine(function () {
            yield 42;
        });

        $promise->then(null, $this->expectCallableOnceWith(new \UnexpectedValueException('Expected coroutine to yield React\Promise\PromiseInterface, but got integer')));
    }
}
