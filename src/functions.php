<?php

namespace React\Async;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Block waiting for the given `$promise` to be fulfilled.
 *
 * ```php
 * $result = React\Async\await($promise, $loop);
 * ```
 *
 * This function will only return after the given `$promise` has settled, i.e.
 * either fulfilled or rejected.
 *
 * While the promise is pending, this function will assume control over the event
 * loop. Internally, it will `run()` the [default loop](https://github.com/reactphp/event-loop#loop)
 * until the promise settles and then calls `stop()` to terminate execution of the
 * loop. This means this function is more suited for short-lived promise executions
 * when using promise-based APIs is not feasible. For long-running applications,
 * using promise-based APIs by leveraging chained `then()` calls is usually preferable.
 *
 * Once the promise is fulfilled, this function will return whatever the promise
 * resolved to.
 *
 * Once the promise is rejected, this will throw whatever the promise rejected
 * with. If the promise did not reject with an `Exception` or `Throwable` (PHP 7+),
 * then this function will throw an `UnexpectedValueException` instead.
 *
 * ```php
 * try {
 *     $result = React\Async\await($promise, $loop);
 *     // promise successfully fulfilled with $result
 *     echo 'Result: ' . $result;
 * } catch (Throwable $e) {
 *     // promise rejected with $e
 *     echo 'Error: ' . $e->getMessage();
 * }
 * ```
 *
 * @param PromiseInterface $promise
 * @return mixed returns whatever the promise resolves to
 * @throws \Exception when the promise is rejected with an `Exception`
 * @throws \Throwable when the promise is rejected with a `Throwable` (PHP 7+)
 * @throws \UnexpectedValueException when the promise is rejected with an unexpected value (Promise API v1 or v2 only)
 */
function await(PromiseInterface $promise)
{
    $wait = true;
    $resolved = null;
    $exception = null;
    $rejected = false;
    $loopStarted = false;

    $promise->then(
        function ($c) use (&$resolved, &$wait, &$loopStarted) {
            $resolved = $c;
            $wait = false;

            if ($loopStarted) {
                Loop::stop();
            }
        },
        function ($error) use (&$exception, &$rejected, &$wait, &$loopStarted) {
            $exception = $error;
            $rejected = true;
            $wait = false;

            if ($loopStarted) {
                Loop::stop();
            }
        }
    );

    // Explicitly overwrite argument with null value. This ensure that this
    // argument does not show up in the stack trace in PHP 7+ only.
    $promise = null;

    while ($wait) {
        $loopStarted = true;
        Loop::run();
    }

    if ($rejected) {
        // promise is rejected with an unexpected value (Promise API v1 or v2 only)
        if (!$exception instanceof \Exception && !$exception instanceof \Throwable) {
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected value of type ' . (is_object($exception) ? get_class($exception) : gettype($exception))
            );
        }

        throw $exception;
    }

    return $resolved;
}

/**
 * @param array<callable():PromiseInterface<mixed,Exception>> $tasks
 * @return PromiseInterface<array<mixed>,Exception>
 */
function parallel(array $tasks)
{
    $pending = array();
    $deferred = new Deferred(function () use (&$pending) {
        foreach ($pending as $promise) {
            if ($promise instanceof PromiseInterface && \method_exists($promise, 'cancel')) {
                $promise->cancel();
            }
        }
        $pending = array();
    });
    $results = array();
    $errored = false;

    $numTasks = count($tasks);
    if (0 === $numTasks) {
        $deferred->resolve($results);
    }

    $taskErrback = function ($error) use (&$pending, $deferred, &$errored) {
        $errored = true;
        $deferred->reject($error);

        foreach ($pending as $promise) {
            if ($promise instanceof PromiseInterface && \method_exists($promise, 'cancel')) {
                $promise->cancel();
            }
        }
        $pending = array();
    };

    foreach ($tasks as $i => $task) {
        $taskCallback = function ($result) use (&$results, &$pending, $numTasks, $i, $deferred) {
            $results[$i] = $result;

            if (count($results) === $numTasks) {
                $deferred->resolve($results);
            }
        };

        $promise = call_user_func($task);
        assert($promise instanceof PromiseInterface);
        $pending[$i] = $promise;

        $promise->then($taskCallback, $taskErrback);

        if ($errored) {
            break;
        }
    }

    return $deferred->promise();
}

/**
 * @param array<callable():PromiseInterface<mixed,Exception>> $tasks
 * @return PromiseInterface<array<mixed>,Exception>
 */
function series(array $tasks)
{
    $pending = null;
    $deferred = new Deferred(function () use (&$pending) {
        if ($pending instanceof PromiseInterface && \method_exists($pending, 'cancel')) {
            $pending->cancel();
        }
        $pending = null;
    });
    $results = array();

    /** @var callable():void $next */
    $taskCallback = function ($result) use (&$results, &$next) {
        $results[] = $result;
        $next();
    };

    $next = function () use (&$tasks, $taskCallback, $deferred, &$results, &$pending) {
        if (0 === count($tasks)) {
            $deferred->resolve($results);
            return;
        }

        $task = array_shift($tasks);
        $promise = call_user_func($task);
        assert($promise instanceof PromiseInterface);
        $pending = $promise;

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
    $pending = null;
    $deferred = new Deferred(function () use (&$pending) {
        if ($pending instanceof PromiseInterface && \method_exists($pending, 'cancel')) {
            $pending->cancel();
        }
        $pending = null;
    });

    /** @var callable $next */
    $next = function ($value = null) use (&$tasks, &$next, $deferred, &$pending) {
        if (0 === count($tasks)) {
            $deferred->resolve($value);
            return;
        }

        $task = array_shift($tasks);
        $promise = call_user_func_array($task, func_get_args());
        assert($promise instanceof PromiseInterface);
        $pending = $promise;

        $promise->then($next, array($deferred, 'reject'));
    };

    $next();

    return $deferred->promise();
}
