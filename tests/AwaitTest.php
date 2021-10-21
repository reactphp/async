<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise;
use React\Promise\Deferred;

class AwaitTest extends TestCase
{
    public function testAwaitOneRejected()
    {
        $promise = $this->createPromiseRejected(new \Exception('test'));

        $this->setExpectedException('Exception', 'test');
        React\Async\await($promise);
    }

    public function testAwaitOneRejectedWithFalseWillWrapInUnexpectedValueException()
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        $promise = Promise\reject(false);

        $this->setExpectedException('UnexpectedValueException', 'Promise rejected with unexpected value of type bool');
        React\Async\await($promise);
    }

    public function testAwaitOneRejectedWithNullWillWrapInUnexpectedValueException()
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        $promise = Promise\reject(null);

        $this->setExpectedException('UnexpectedValueException', 'Promise rejected with unexpected value of type NULL');
        React\Async\await($promise);
    }

    /**
     * @requires PHP 7
     */
    public function testAwaitRejectedWithPhp7ErrorWillThrowOriginalError()
    {
        $promise = Promise\reject(new \Error('Test', 42));

        $this->setExpectedException('Error', 'Test', 42);
        React\Async\await($promise);
    }

    public function testAwaitOneResolved()
    {
        $promise = $this->createPromiseResolved(2);

        $this->assertEquals(2, React\Async\await($promise));
    }

    public function testAwaitReturnsFulfilledValueWithoutGivingLoop()
    {
        $promise = Promise\resolve(42);

        $this->assertEquals(42, React\Async\await($promise));
    }

    public function testAwaitOneInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, React\Async\await($promise));
    }

    public function testAwaitOneResolvesShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('React\Promise\When') && PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API with PHP 5.4+');
        }

        gc_collect_cycles();

        $promise = Promise\resolve(1);
        React\Async\await($promise);
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testAwaitOneRejectedShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('React\Promise\When') && PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API with PHP 5.4+');
        }

        gc_collect_cycles();

        $promise = Promise\reject(new \RuntimeException());
        try {
            React\Async\await($promise);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testAwaitNullValueShouldNotCreateAnyGarbageReferences()
    {
        if (!interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Promises must be rejected with a \Throwable instance since Promise v3');
        }

        if (class_exists('React\Promise\When') && PHP_VERSION_ID >= 50400) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API with PHP 5.4+');
        }

        gc_collect_cycles();

        $promise = Promise\reject(null);
        try {
            React\Async\await($promise);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }

    protected function createPromiseResolved($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        Loop::addTimer($delay, function () use ($deferred, $value) {
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }

    protected function createPromiseRejected($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        Loop::addTimer($delay, function () use ($deferred, $value) {
            $deferred->reject($value);
        });

        return $deferred->promise();
    }

    protected function createTimerInterrupt($delay = 0.01)
    {
        Loop::addTimer($delay, function () {
            Loop::stop();
        });
    }

    public function setExpectedException($exception, $exceptionMessage = '', $exceptionCode = null)
    {
        if (method_exists($this, 'expectException')) {
            // PHPUnit 5+
            $this->expectException($exception);
            if ($exceptionMessage !== '') {
                $this->expectExceptionMessage($exceptionMessage);
            }
            if ($exceptionCode !== null) {
                $this->expectExceptionCode($exceptionCode);
            }
        } else {
            // legacy PHPUnit 4
            parent::setExpectedException($exception, $exceptionMessage, $exceptionCode);
        }
    }
}
