<?php

namespace React\Async;

/**
 * @param array<callable> $tasks
 * @param ?callable       $callback
 * @param ?callable       $errback
 * @return void
 */
function parallel(array $tasks, $callback = null, $errback = null)
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

    $checkDone = function () use (&$results, &$errors, $numTasks, $done) {
        if ($numTasks === count($results) + count($errors)) {
            $done();
        }
    };

    $taskErrback = function ($error) use (&$errors, $checkDone) {
        $errors[] = $error;
        $checkDone();
    };

    foreach ($tasks as $i => $task) {
        $taskCallback = function ($result) use (&$results, $i, $checkDone) {
            $results[$i] = $result;
            $checkDone();
        };

        call_user_func($task, $taskCallback, $taskErrback);
    }
}

/**
 * @param array<callable> $tasks
 * @param ?callable       $callback
 * @param ?callable       $errback
 * @return void
 */
function series(array $tasks, $callback = null, $errback = null)
{
    $results = array();

    /** @var callable():void $next */
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

/**
 * @param array<callable> $tasks
 * @param ?callable       $callback
 * @param ?callable       $errback
 * @return void
 */
function waterfall(array $tasks, $callback = null, $errback = null)
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
