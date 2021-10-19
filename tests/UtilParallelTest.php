<?php

namespace React\Tests\Async;

use React\Async\Util;

class UtilParallelTest extends TestCase
{
    public function testParallelWithoutTasks()
    {
        $tasks = array();

        $callback = $this->createCallableMock($this->once(), array());
        $errback = $this->createCallableMock($this->never());

        Util::parallel($tasks, $callback, $errback);
    }

    public function testParallelWithTasks()
    {
        $loop = new \React\EventLoop\StreamSelectLoop();

        $tasks = array(
            function ($callback, $errback) use ($loop) {
                $loop->addTimer(0.1, function () use ($callback) {
                    $callback('foo');
                });
            },
            function ($callback, $errback) use ($loop) {
                $loop->addTimer(0.1, function () use ($callback) {
                    $callback('bar');
                });
            },
        );

        $callback = $this->createCallableMock($this->once(), array('foo', 'bar'));
        $errback = $this->createCallableMock($this->never());

        Util::parallel($tasks, $callback, $errback);

        $timer = new Timer($this);
        $timer->start();

        $loop->run();

        $timer->stop();
        $timer->assertInRange(0.1, 0.2);
    }

    public function testParallelWithError()
    {
        $called = 0;

        $tasks = array(
            function ($callback, $errback) use (&$called) {
                $callback('foo');
                $called++;
            },
            function ($callback, $errback) {
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

        Util::parallel($tasks, $callback, $errback);

        $this->assertSame(2, $called);
    }

    public function testParallelWithDelayedError()
    {
        $called = 0;

        $loop = new \React\EventLoop\StreamSelectLoop();

        $tasks = array(
            function ($callback, $errback) use (&$called) {
                $callback('foo');
                $called++;
            },
            function ($callback, $errback) use ($loop) {
                $loop->addTimer(0.001, function () use ($errback) {
                    $e = new \RuntimeException('whoops');
                    $errback($e);
                });
            },
            function ($callback, $errback) use (&$called) {
                $callback('bar');
                $called++;
            },
        );

        $callback = $this->createCallableMock($this->never());
        $errback = $this->createCallableMock($this->once());

        Util::parallel($tasks, $callback, $errback);

        $loop->run();

        $this->assertSame(2, $called);
    }
}
