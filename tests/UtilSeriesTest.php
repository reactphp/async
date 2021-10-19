<?php

namespace React\Tests\Async;

use React\Async\Util;

class UtilSeriesTest extends TestCase
{
    public function testSeriesWithoutTasks()
    {
        $tasks = array();

        $callback = $this->createCallableMock($this->once(), array());
        $errback = $this->createCallableMock($this->never());

        Util::series($tasks, $callback, $errback);
    }

    public function testSeriesWithTasks()
    {
        $loop = new \React\EventLoop\StreamSelectLoop();

        $tasks = array(
            function ($callback, $errback) use ($loop) {
                $loop->addTimer(0.05, function () use ($callback) {
                    $callback('foo');
                });
            },
            function ($callback, $errback) use ($loop) {
                $loop->addTimer(0.05, function () use ($callback) {
                    $callback('bar');
                });
            },
        );

        $callback = $this->createCallableMock($this->once(), array('foo', 'bar'));
        $errback = $this->createCallableMock($this->never());

        Util::series($tasks, $callback, $errback);

        $timer = new Timer($this);
        $timer->start();

        $loop->run();

        $timer->stop();
        $timer->assertInRange(0.10, 0.20);
    }

    public function testSeriesWithError()
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

        Util::series($tasks, $callback, $errback);

        $this->assertSame(1, $called);
    }
}
