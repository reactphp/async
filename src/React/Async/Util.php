<?php

namespace React\Async;

class Util
{
    public static function series($tasks, $callback, $errback)
    {
        $results = array();

        $taskCallback = function ($result) use (&$results, &$next) {
            $results[] = $result;
            $next();
        };

        $done = function () use (&$results, $callback) {
            call_user_func($callback, $results);
        };

        $next = function () use (&$tasks, $taskCallback, $errback, $done) {
            if (0 === count($tasks)) {
                $done();
                return;
            }

            $task = array_shift($tasks);
            call_user_func($task, $taskCallback, $errback);
        };

        $next();
    }

    public static function parallel($tasks, $callback, $errback)
    {
        $results = array();
        $errors = array();

        $taskErrback = function ($error) use (&$errors, &$checkDone) {
            $errors[] = $error;
            $checkDone();
        };

        $done = function () use (&$results, &$errors, $callback, $errback) {
            if (count($errors)) {
                $errback(array_shift($errors));
                return;
            }

            $callback($results);
        };

        $numTasks = count($tasks);

        if (0 === $numTasks) {
            $done();
            return;
        }

        $checkDone = function () use (&$results, &$errors, $numTasks, $done) {
            if ($numTasks === count($results) + count($errors)) {
                $done();
            }
        };

        foreach ($tasks as $i => $task) {
            $taskCallback = function ($result) use (&$results, $i, $checkDone) {
                $results[$i] = $result;
                $checkDone();
            };

            call_user_func($task, $taskCallback, $taskErrback);
        }
    }
}
