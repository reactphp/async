<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;
use React\Promise\Promise;

class WaterfallTest extends TestCase
{
    public function testWaterfallWithoutTasks()
    {
        $tasks = array();

        $promise = React\Async\waterfall($tasks);

        $promise->then($this->expectCallableOnceWith(null));
    }

    public function testWaterfallWithTasks()
    {
        $tasks = array(
            function ($foo = 'foo') {
                return new Promise(function ($resolve) use ($foo) {
                    Loop::addTimer(0.05, function () use ($resolve, $foo) {
                        $resolve($foo);
                    });
                });
            },
            function ($foo) {
                return new Promise(function ($resolve) use ($foo) {
                    Loop::addTimer(0.05, function () use ($resolve, $foo) {
                        $resolve($foo . 'bar');
                    });
                });
            },
            function ($bar) {
                return new Promise(function ($resolve) use ($bar) {
                    Loop::addTimer(0.05, function () use ($resolve, $bar) {
                        $resolve($bar . 'baz');
                    });
                });
            },
        );

        $promise = React\Async\waterfall($tasks);

        $promise->then($this->expectCallableOnceWith('foobarbaz'));

        $timer = new Timer($this);
        $timer->start();

        Loop::run();

        $timer->stop();
        $timer->assertInRange(0.15, 0.30);
    }

    public function testWaterfallWithError()
    {
        $called = 0;

        $tasks = array(
            function () use (&$called) {
                $called++;
                return new Promise(function ($resolve) {
                    $resolve('foo');
                });
            },
            function ($foo) {
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

        $promise = React\Async\waterfall($tasks);

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException('whoops')));

        $this->assertSame(1, $called);
    }

    public function testWaterfallWillCancelFirstPendingPromiseWhenCallingCancelOnResultingPromise()
    {
        $cancelled = 0;

        $tasks = array(
            function () {
                return new Promise(function ($resolve) {
                    $resolve();
                });
            },
            function () use (&$cancelled) {
                return new Promise(function () { }, function () use (&$cancelled) {
                    $cancelled++;
                });
            }
        );

        $promise = React\Async\waterfall($tasks);
        $promise->cancel();

        $this->assertSame(1, $cancelled);
    }
}
