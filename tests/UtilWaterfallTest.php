<?php

namespace React\Tests\Async;

use React\Async\Util;

class UtilWaterfallTest extends TestCase
{
    public function testWaterfallWithoutTasks()
    {
        $tasks = array();

        $callback = $this->createCallableMock($this->once(), array());
        $errback = $this->createCallableMock($this->never());

        Util::waterfall($tasks, $callback, $errback);
    }

    public function testWaterfallWithTasks()
    {
        $loop = new \React\EventLoop\StreamSelectLoop();

        $tasks = array(
            function ($callback, $errback) use ($loop) {
                $loop->addTimer(0.05, function () use ($callback) {
                    $callback('foo');
                });
            },
            function ($foo, $callback, $errback) use ($loop) {
                $loop->addTimer(0.05, function () use ($callback, $foo) {
                    $callback($foo.'bar');
                });
            },
            function ($bar, $callback, $errback) use ($loop) {
                $loop->addTimer(0.05, function () use ($callback, $bar) {
                    $callback($bar.'baz');
                });
            },
        );

        $callback = $this->createCallableMock($this->once(), 'foobarbaz');
        $errback = $this->createCallableMock($this->never());

        Util::waterfall($tasks, $callback, $errback);

        $timer = new Timer($this);
        $timer->start();

        $loop->run();

        $timer->stop();
        $timer->assertInRange(0.15, 0.30);
    }

    public function testWaterfallWithError()
    {
        $called = 0;

        $tasks = array(
            function ($callback, $errback) use (&$called) {
                $callback('foo');
                $called++;
            },
            function ($foo, $callback, $errback) {
                $e = new \RuntimeException('whoops');
                $errback($e);
            },
            function ($callback, $errback) use (&$called) {
                $callback('bar');
                $called++;
            },
        );

        $callback = $this->createCallableMock($this->never());
        $errback = $this->createCallableMock($this->once());

        Util::waterfall($tasks, $callback, $errback);

        $this->assertSame(1, $called);
    }
}
