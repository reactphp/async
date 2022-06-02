<?php

namespace React\Async;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\reject;
use function React\Promise\resolve;

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
 * with. If the promise did not reject with an `Exception` or `Throwable`, then
 * this function will throw an `UnexpectedValueException` instead.
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
 * @throws \Throwable when the promise is rejected with a `Throwable`
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
        if (!$exception instanceof \Throwable) {
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected value of type ' . (is_object($exception) ? get_class($exception) : gettype($exception))
            );
        }

        throw $exception;
    }

    return $resolved;
}


/**
 * Execute a Generator-based coroutine to "await" promises.
 *
 * ```php
 * React\Async\coroutine(function () {
 *     $browser = new React\Http\Browser();
 *
 *     try {
 *         $response = yield $browser->get('https://example.com/');
 *         assert($response instanceof Psr\Http\Message\ResponseInterface);
 *         echo $response->getBody();
 *     } catch (Exception $e) {
 *         echo 'Error: ' . $e->getMessage() . PHP_EOL;
 *     }
 * });
 * ```
 *
 * Using Generator-based coroutines is an alternative to directly using the
 * underlying promise APIs. For many use cases, this makes using promise-based
 * APIs much simpler, as it resembles a synchronous code flow more closely.
 * The above example performs the equivalent of directly using the promise APIs:
 *
 * ```php
 * $browser = new React\Http\Browser();
 *
 * $browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
 *     echo $response->getBody();
 * }, function (Exception $e) {
 *     echo 'Error: ' . $e->getMessage() . PHP_EOL;
 * });
 * ```
 *
 * The `yield` keyword can be used to "await" a promise resolution. Internally,
 * it will turn the entire given `$function` into a [`Generator`](https://www.php.net/manual/en/class.generator.php).
 * This allows the execution to be interrupted and resumed at the same place
 * when the promise is fulfilled. The `yield` statement returns whatever the
 * promise is fulfilled with. If the promise is rejected, it will throw an
 * `Exception` or `Throwable`.
 *
 * The `coroutine()` function will always return a Proimise which will be
 * fulfilled with whatever your `$function` returns. Likewise, it will return
 * a promise that will be rejected if you throw an `Exception` or `Throwable`
 * from your `$function`. This allows you easily create Promise-based functions:
 *
 * ```php
 * $promise = React\Async\coroutine(function () {
 *     $browser = new React\Http\Browser();
 *     $urls = [
 *         'https://example.com/alice',
 *         'https://example.com/bob'
 *     ];
 *
 *     $bytes = 0;
 *     foreach ($urls as $url) {
 *         $response = yield $browser->get($url);
 *         assert($response instanceof Psr\Http\Message\ResponseInterface);
 *         $bytes += $response->getBody()->getSize();
 *     }
 *     return $bytes;
 * });
 *
 * $promise->then(function (int $bytes) {
 *     echo 'Total size: ' . $bytes . PHP_EOL;
 * }, function (Exception $e) {
 *     echo 'Error: ' . $e->getMessage() . PHP_EOL;
 * });
 * ```
 *
 * The previous example uses a `yield` statement inside a loop to highlight how
 * this vastly simplifies consuming asynchronous operations. At the same time,
 * this naive example does not leverage concurrent execution, as it will
 * essentially "await" between each operation. In order to take advantage of
 * concurrent execution within the given `$function`, you can "await" multiple
 * promises by using a single `yield` together with Promise-based primitives
 * like this:
 *
 * ```php
 * $promise = React\Async\coroutine(function () {
 *     $browser = new React\Http\Browser();
 *     $urls = [
 *         'https://example.com/alice',
 *         'https://example.com/bob'
 *     ];
 *
 *     $promises = [];
 *     foreach ($urls as $url) {
 *         $promises[] = $browser->get($url);
 *     }
 *
 *     try {
 *         $responses = yield React\Promise\all($promises);
 *     } catch (Exception $e) {
 *         foreach ($promises as $promise) {
 *             $promise->cancel();
 *         }
 *         throw $e;
 *     }
 *
 *     $bytes = 0;
 *     foreach ($responses as $response) {
 *         assert($response instanceof Psr\Http\Message\ResponseInterface);
 *         $bytes += $response->getBody()->getSize();
 *     }
 *     return $bytes;
 * });
 *
 * $promise->then(function (int $bytes) {
 *     echo 'Total size: ' . $bytes . PHP_EOL;
 * }, function (Exception $e) {
 *     echo 'Error: ' . $e->getMessage() . PHP_EOL;
 * });
 * ```
 *
 * @param callable(...$args):\Generator<mixed,PromiseInterface,mixed,mixed> $function
 * @param mixed ...$args Optional list of additional arguments that will be passed to the given `$function` as is
 * @return PromiseInterface<mixed>
 * @since 3.0.0
 */
function coroutine(callable $function, ...$args): PromiseInterface
{
    try {
        $generator = $function(...$args);
    } catch (\Throwable $e) {
        return reject($e);
    }

    if (!$generator instanceof \Generator) {
        return resolve($generator);
    }

    $promise = null;
    $deferred = new Deferred(function () use (&$promise) {
        if ($promise instanceof PromiseInterface && \method_exists($promise, 'cancel')) {
            $promise->cancel();
        }
        $promise = null;
    });

    /** @var callable $next */
    $next = function () use ($deferred, $generator, &$next, &$promise) {
        try {
            if (!$generator->valid()) {
                $next = null;
                $deferred->resolve($generator->getReturn());
                return;
            }
        } catch (\Throwable $e) {
            $next = null;
            $deferred->reject($e);
            return;
        }

        $promise = $generator->current();
        if (!$promise instanceof PromiseInterface) {
            $next = null;
            $deferred->reject(new \UnexpectedValueException(
                'Expected coroutine to yield ' . PromiseInterface::class . ', but got ' . (is_object($promise) ? get_class($promise) : gettype($promise))
            ));
            return;
        }

        $promise->then(function ($value) use ($generator, $next) {
            $generator->send($value);
            $next();
        }, function (\Throwable $reason) use ($generator, $next) {
            $generator->throw($reason);
            $next();
        })->then(null, function (\Throwable $reason) use ($deferred, &$next) {
            $next = null;
            $deferred->reject($reason);
        });
    };
    $next();

    return $deferred->promise();
}

/**
 * @param iterable<callable():PromiseInterface<mixed,Exception>> $tasks
 * @return PromiseInterface<array<mixed>,Exception>
 */
function parallel(iterable $tasks): PromiseInterface
{
    $pending = [];
    $deferred = new Deferred(function () use (&$pending) {
        foreach ($pending as $promise) {
            if ($promise instanceof PromiseInterface && \method_exists($promise, 'cancel')) {
                $promise->cancel();
            }
        }
        $pending = [];
    });
    $results = [];
    $continue = true;

    $taskErrback = function ($error) use (&$pending, $deferred, &$continue) {
        $continue = false;
        $deferred->reject($error);

        foreach ($pending as $promise) {
            if ($promise instanceof PromiseInterface && \method_exists($promise, 'cancel')) {
                $promise->cancel();
            }
        }
        $pending = [];
    };

    foreach ($tasks as $i => $task) {
        $taskCallback = function ($result) use (&$results, &$pending, &$continue, $i, $deferred) {
            $results[$i] = $result;
            unset($pending[$i]);

            if (!$pending && !$continue) {
                $deferred->resolve($results);
            }
        };

        $promise = \call_user_func($task);
        assert($promise instanceof PromiseInterface);
        $pending[$i] = $promise;

        $promise->then($taskCallback, $taskErrback);

        if (!$continue) {
            break;
        }
    }

    $continue = false;
    if (!$pending) {
        $deferred->resolve($results);
    }

    return $deferred->promise();
}

/**
 * @param iterable<callable():PromiseInterface<mixed,Exception>> $tasks
 * @return PromiseInterface<array<mixed>,Exception>
 */
function series(iterable $tasks): PromiseInterface
{
    $pending = null;
    $deferred = new Deferred(function () use (&$pending) {
        if ($pending instanceof PromiseInterface && \method_exists($pending, 'cancel')) {
            $pending->cancel();
        }
        $pending = null;
    });
    $results = [];

    if ($tasks instanceof \IteratorAggregate) {
        $tasks = $tasks->getIterator();
        assert($tasks instanceof \Iterator);
    }

    /** @var callable():void $next */
    $taskCallback = function ($result) use (&$results, &$next) {
        $results[] = $result;
        $next();
    };

    $next = function () use (&$tasks, $taskCallback, $deferred, &$results, &$pending) {
        if ($tasks instanceof \Iterator ? !$tasks->valid() : !$tasks) {
            $deferred->resolve($results);
            return;
        }

        if ($tasks instanceof \Iterator) {
            $task = $tasks->current();
            $tasks->next();
        } else {
            $task = \array_shift($tasks);
        }

        $promise = \call_user_func($task);
        assert($promise instanceof PromiseInterface);
        $pending = $promise;

        $promise->then($taskCallback, array($deferred, 'reject'));
    };

    $next();

    return $deferred->promise();
}

/**
 * @param iterable<callable(mixed=):PromiseInterface<mixed,Exception>> $tasks
 * @return PromiseInterface<mixed,Exception>
 */
function waterfall(iterable $tasks): PromiseInterface
{
    $pending = null;
    $deferred = new Deferred(function () use (&$pending) {
        if ($pending instanceof PromiseInterface && \method_exists($pending, 'cancel')) {
            $pending->cancel();
        }
        $pending = null;
    });

    if ($tasks instanceof \IteratorAggregate) {
        $tasks = $tasks->getIterator();
        assert($tasks instanceof \Iterator);
    }

    /** @var callable $next */
    $next = function ($value = null) use (&$tasks, &$next, $deferred, &$pending) {
        if ($tasks instanceof \Iterator ? !$tasks->valid() : !$tasks) {
            $deferred->resolve($value);
            return;
        }

        if ($tasks instanceof \Iterator) {
            $task = $tasks->current();
            $tasks->next();
        } else {
            $task = \array_shift($tasks);
        }

        $promise = \call_user_func_array($task, func_get_args());
        assert($promise instanceof PromiseInterface);
        $pending = $promise;

        $promise->then($next, array($deferred, 'reject'));
    };

    $next();

    return $deferred->promise();
}
