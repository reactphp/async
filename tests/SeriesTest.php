<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;

class SeriesTest extends TestCase
{
    public function testSeriesWithoutTasks()
    {
        $tasks = array();

        $promise = React\Async\series($tasks);

        $promise->then($this->expectCallableOnceWith(array()));
    }

    public function testSeriesWithTasks()
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

    public function testSeriesWithError()
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

    public function testSeriesWillCancelFirstPendingPromiseWhenCallingCancelOnResultingPromise()
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
        $promise->cancel();

        $this->assertSame(1, $cancelled);
    }
}
