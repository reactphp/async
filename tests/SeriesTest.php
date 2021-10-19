<?php

namespace React\Tests\Async;

use React;
use React\EventLoop\Loop;

class SeriesTest extends TestCase
{
    public function testSeriesWithoutTasks()
    {
        $tasks = array();

        $callback = $this->expectCallableOnceWith(array());
        $errback = $this->expectCallableNever();

        React\Async\series($tasks, $callback, $errback);
    }

    public function testSeriesWithTasks()
    {
        $tasks = array(
            function ($callback, $errback) {
                Loop::addTimer(0.05, function () use ($callback) {
                    $callback('foo');
                });
            },
            function ($callback, $errback) {
                Loop::addTimer(0.05, function () use ($callback) {
                    $callback('bar');
                });
            },
        );

        $callback = $this->expectCallableOnceWith(array('foo', 'bar'));
        $errback = $this->expectCallableNever();

        React\Async\series($tasks, $callback, $errback);

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

        React\Async\series($tasks, $callback, $errback);

        $this->assertSame(1, $called);
    }
}
