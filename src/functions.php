<?php

namespace React\Async;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * @param array<callable():PromiseInterface<mixed,Exception>> $tasks
 * @return PromiseInterface<array<mixed>,Exception>
 */
function parallel(array $tasks)
{
    $deferred = new Deferred();
    $results = array();
    $errors = array();

    $done = function () use (&$results, &$errors, $deferred) {
        if (count($errors)) {
            $deferred->reject(array_shift($errors));
            return;
        }

        $deferred->resolve($results);
    };

    $numTasks = count($tasks);

    if (0 === $numTasks) {
        $done();
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

        $promise = call_user_func($task);
        assert($promise instanceof PromiseInterface);

        $promise->then($taskCallback, $taskErrback);
    }

    return $deferred->promise();
}

/**
 * @param array<callable():PromiseInterface<mixed,Exception>> $tasks
 * @return PromiseInterface<array<mixed>,Exception>
 */
function series(array $tasks)
{
    $deferred = new Deferred();
    $results = array();

    /** @var callable():void $next */
    $taskCallback = function ($result) use (&$results, &$next) {
        $results[] = $result;
        $next();
    };

    $next = function () use (&$tasks, $taskCallback, $deferred, &$results) {
        if (0 === count($tasks)) {
            $deferred->resolve($results);
            return;
        }

        $task = array_shift($tasks);
        $promise = call_user_func($task);
        assert($promise instanceof PromiseInterface);

        $promise->then($taskCallback, array($deferred, 'reject'));
    };

    $next();

    return $deferred->promise();
}

/**
 * @param array<callable(mixed=):PromiseInterface<mixed,Exception>> $tasks
 * @return PromiseInterface<mixed,Exception>
 */
function waterfall(array $tasks)
{
    $deferred = new Deferred();

    /** @var callable $next */
    $next = function ($value = null) use (&$tasks, &$next, $deferred) {
        if (0 === count($tasks)) {
            $deferred->resolve($value);
            return;
        }

        $task = array_shift($tasks);
        $promise = call_user_func_array($task, func_get_args());
        assert($promise instanceof PromiseInterface);

        $promise->then($next, array($deferred, 'reject'));
    };

    $next();

    return $deferred->promise();
}
