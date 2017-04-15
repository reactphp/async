<?php

namespace React\Async;

class Util
{
    public static function series($tasks, $callback = null, $errback = null)
    {
        $results = array();

        $taskCallback = function ($result) use (&$results, &$next) {
            $results[] = $result;
            $next();
        };

        $done = function () use (&$results, $callback) {
            if ($callback) {
                call_user_func($callback, $results);
            }
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

    public static function parallel($tasks, $callback = null, $errback = null)
    {
        $results = array();
        $errors = array();

        $done = function () use (&$results, &$errors, $callback, $errback) {
            if (!$callback) {
                return;
            }

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

        $checkDone = function($results, $errors) use ($numTasks, $done) {
            if ($numTasks === count($results) + count($errors)) {
                $done();
            }
        };

        foreach ($tasks as $i => $task) {
            $taskCallback = function ($result) use (&$results, &$errors, $i, $checkDone) {
                $results[$i] = $result;
                $checkDone($results, $errors);
            };

            $taskErrback = function ($error) use (&$results, &$errors, $i, $checkDone) {
                $errors[$i] = $error;
                $checkDone($results, $errors);
            };

            call_user_func($task, $taskCallback, $taskErrback);
        }
    }

    public static function waterfall($tasks, $callback = null, $errback = null)
    {
        $taskCallback = function () use (&$next) {
            call_user_func_array($next, func_get_args());
        };

        $done = function () use ($callback) {
            if ($callback) {
                call_user_func_array($callback, func_get_args());
            }
        };

        $next = function () use (&$tasks, $taskCallback, $errback, $done) {
            if (0 === count($tasks)) {
                call_user_func_array($done, func_get_args());
                return;
            }

            $task = array_shift($tasks);
            $args = array_merge(func_get_args(), array($taskCallback, $errback));
            call_user_func_array($task, $args);
        };

        $next();
    }
}
