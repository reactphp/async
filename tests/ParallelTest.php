<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;

class ParallelTest extends TestCase
{
    public function testParallelWithoutTasks()
    {
        $tasks = array();

        $promise = React\Async\parallel($tasks);

        $promise->then($this->expectCallableOnceWith(array()));
    }

    public function testParallelWithTasks()
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

    public function testParallelWithErrorReturnsPromiseRejectedWithExceptionFromTaskAndStopsCallingAdditionalTasks()
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

    public function testParallelWithDelayedErrorReturnsPromiseRejectedWithExceptionFromTask()
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
