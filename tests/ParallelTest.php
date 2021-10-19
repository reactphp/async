<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;

class ParallelTest extends TestCase
{
    public function testParallelWithoutTasks()
    {
        $tasks = array();

        $callback = $this->expectCallableOnceWith(array());
        $errback = $this->expectCallableNever();

        React\Async\parallel($tasks, $callback, $errback);
    }

    public function testParallelWithTasks()
    {
        $tasks = array(
            function ($callback, $errback) {
                Loop::addTimer(0.1, function () use ($callback) {
                    $callback('foo');
                });
            },
            function ($callback, $errback) {
                Loop::addTimer(0.1, function () use ($callback) {
                    $callback('bar');
                });
            },
        );

        $callback = $this->expectCallableOnceWith(array('foo', 'bar'));
        $errback = $this->expectCallableNever();

        React\Async\parallel($tasks, $callback, $errback);

        $timer = new Timer($this);
        $timer->start();

        Loop::run();

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

        $callback = $this->expectCallableNever();
        $errback = $this->expectCallableOnce();

        React\Async\parallel($tasks, $callback, $errback);

        $this->assertSame(2, $called);
    }

    public function testParallelWithDelayedError()
    {
        $called = 0;

        $tasks = array(
            function ($callback, $errback) use (&$called) {
                $callback('foo');
                $called++;
            },
            function ($callback, $errback) {
                Loop::addTimer(0.001, function () use ($errback) {
                    $e = new \RuntimeException('whoops');
                    $errback($e);
                });
            },
            function ($callback, $errback) use (&$called) {
                $callback('bar');
                $called++;
            },
        );

        $callback = $this->expectCallableNever();
        $errback = $this->expectCallableOnce();

        React\Async\parallel($tasks, $callback, $errback);

        Loop::run();

        $this->assertSame(2, $called);
    }
}
