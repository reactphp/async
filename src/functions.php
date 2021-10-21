<?php

namespace React\Async;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Promise\Timer;

/**
 * Block waiting for the given `$promise` to be fulfilled.
 *
 * ```php
 * $result = React\Async\await($promise, $loop);
 * ```
 *
 * This function will only return after the given `$promise` has settled, i.e.
 * either fulfilled or rejected. In the meantime, the event loop will run any
 * events attached to the same loop until the promise settles.
 *
 * Once the promise is fulfilled, this function will return whatever the promise
 * resolved to.
 *
 * Once the promise is rejected, this will throw whatever the promise rejected
 * with. If the promise did not reject with an `Exception`, then this function
 * will throw an `UnexpectedValueException` instead.
 *
 * ```php
 * try {
 *     $result = React\Async\await($promise, $loop);
 *     // promise successfully fulfilled with $result
 *     echo 'Result: ' . $result;
 * } catch (Exception $exception) {
 *     // promise rejected with $exception
 *     echo 'ERROR: ' . $exception->getMessage();
 * }
 * ```
 *
 * This function takes an optional `LoopInterface|null $loop` parameter that can be used to
 * pass the event loop instance to use. You can use a `null` value here in order to
 * use the [default loop](https://github.com/reactphp/event-loop#loop). This value
 * SHOULD NOT be given unless you're sure you want to explicitly use a given event
 * loop instance.
 *
 * If no `$timeout` argument is given and the promise stays pending, then this
 * will potentially wait/block forever until the promise is settled. To avoid
 * this, API authors creating promises are expected to provide means to
 * configure a timeout for the promise instead. For more details, see also the
 * [`timeout()` function](https://github.com/reactphp/promise-timer#timeout).
 *
 * If the deprecated `$timeout` argument is given and the promise is still pending once the
 * timeout triggers, this will `cancel()` the promise and throw a `TimeoutException`.
 * This implies that if you pass a really small (or negative) value, it will still
 * start a timer and will thus trigger at the earliest possible time in the future.
 *
 * Note that this function will assume control over the event loop. Internally, it
 * will actually `run()` the loop until the promise settles and then calls `stop()` to
 * terminate execution of the loop. This means this function is more suited for
 * short-lived promise executions when using promise-based APIs is not feasible.
 * For long-running applications, using promise-based APIs by leveraging chained
 * `then()` calls is usually preferable.
 *
 * @param PromiseInterface $promise
 * @param ?LoopInterface   $loop
 * @param ?float           $timeout [deprecated] (optional) maximum timeout in seconds or null=wait forever
 * @return mixed returns whatever the promise resolves to
 * @throws \Exception when the promise is rejected
 * @throws \React\Promise\Timer\TimeoutException if the $timeout is given and triggers
 */
function await(PromiseInterface $promise, LoopInterface $loop = null, $timeout = null)
{
    $wait = true;
    $resolved = null;
    $exception = null;
    $rejected = false;
    $loop = $loop ?: Loop::get();

    if ($timeout !== null) {
        $promise = Timer\timeout($promise, $timeout, $loop);
    }

    $promise->then(
        function ($c) use (&$resolved, &$wait, $loop) {
            $resolved = $c;
            $wait = false;
            $loop->stop();
        },
        function ($error) use (&$exception, &$rejected, &$wait, $loop) {
            $exception = $error;
            $rejected = true;
            $wait = false;
            $loop->stop();
        }
    );

    // Explicitly overwrite argument with null value. This ensure that this
    // argument does not show up in the stack trace in PHP 7+ only.
    $promise = null;

    while ($wait) {
        $loop->run();
    }

    if ($rejected) {
        if (!$exception instanceof \Exception && !$exception instanceof \Throwable) {
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected value of type ' . (is_object($exception) ? get_class($exception) : gettype($exception))
            );
        } elseif (!$exception instanceof \Exception) {
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected ' . get_class($exception) . ': ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
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
