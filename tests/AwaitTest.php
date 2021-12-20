<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;

class AwaitTest extends TestCase
{
    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsExceptionWhenPromiseIsRejectedWithException(callable $await)
    {
        $promise = new Promise(function () {
            throw new \Exception('test');
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test');
        $await($promise);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsUnexpectedValueExceptionWhenPromiseIsRejectedWithFalse(callable $await)
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        $promise = new Promise(function ($_, $reject) {
            $reject(false);
        });

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Promise rejected with unexpected value of type bool');
        $await($promise);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsUnexpectedValueExceptionWhenPromiseIsRejectedWithNull(callable $await)
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        $promise = new Promise(function ($_, $reject) {
            $reject(null);
        });

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Promise rejected with unexpected value of type NULL');
        $await($promise);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitThrowsErrorWhenPromiseIsRejectedWithError(callable $await)
    {
        $promise = new Promise(function ($_, $reject) {
            throw new \Error('Test', 42);
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Test');
        $this->expectExceptionCode(42);
        $await($promise);
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitReturnsValueWhenPromiseIsFullfilled(callable $await)
    {
        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });

        $this->assertEquals(42, $await($promise));
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitShouldNotCreateAnyGarbageReferencesForResolvedPromise(callable $await)
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new Promise(function ($resolve) {
            $resolve(42);
        });
        $await($promise);
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitShouldNotCreateAnyGarbageReferencesForRejectedPromise(callable $await)
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new Promise(function () {
            throw new \RuntimeException();
        });
        try {
            $await($promise);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    /**
     * @dataProvider provideAwaiters
     */
    public function testAwaitShouldNotCreateAnyGarbageReferencesForPromiseRejectedWithNullValue(callable $await)
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new Promise(function ($_, $reject) {
            $reject(null);
        });
        try {
            $await($promise);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function provideAwaiters(): iterable
    {
        yield 'await' => [static fn (React\Promise\PromiseInterface $promise): mixed => React\Async\await($promise)];
        yield 'async' => [static fn (React\Promise\PromiseInterface $promise): mixed => React\Async\await(React\Async\async(static fn(): mixed => $promise)())];
    }
}
