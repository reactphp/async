# Async

[![CI status](https://github.com/reactphp/async/workflows/CI/badge.svg)](https://github.com/reactphp/async/actions)

Async utilities for [ReactPHP](https://reactphp.org/).

This library allows you to manage async control flow. It provides a number of
combinators for [Promise](https://github.com/reactphp/promise)-based APIs.
Instead of nesting or chaining promise callbacks, you can declare them as a
list, which is resolved sequentially in an async manner.
React/Async will not automagically change blocking code to be async. You need
to have an actual event loop and non-blocking libraries interacting with that
event loop for it to work. As long as you have a Promise-based API that runs in
an event loop, it can be used with this library.

**Table of Contents**

* [Usage](#usage)
    * [await()](#await)
    * [coroutine()](#coroutine)
    * [parallel()](#parallel)
    * [series()](#series)
    * [waterfall()](#waterfall)
* [Todo](#todo)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Usage

This lightweight library consists only of a few simple functions.
All functions reside under the `React\Async` namespace.

The below examples refer to all functions with their fully-qualified names like this:

```php
React\Async\await(…);
```

As of PHP 5.6+ you can also import each required function into your code like this:

```php
use function React\Async\await;

await(…);
```

Alternatively, you can also use an import statement similar to this:

```php
use React\Async;

Async\await(…);
```

### await()

The `await(PromiseInterface $promise): mixed` function can be used to
block waiting for the given `$promise` to be fulfilled.

```php
$result = React\Async\await($promise);
```

This function will only return after the given `$promise` has settled, i.e.
either fulfilled or rejected.

While the promise is pending, this function will assume control over the event
loop. Internally, it will `run()` the [default loop](https://github.com/reactphp/event-loop#loop)
until the promise settles and then calls `stop()` to terminate execution of the
loop. This means this function is more suited for short-lived promise executions
when using promise-based APIs is not feasible. For long-running applications,
using promise-based APIs by leveraging chained `then()` calls is usually preferable.

Once the promise is fulfilled, this function will return whatever the promise
resolved to.

Once the promise is rejected, this will throw whatever the promise rejected
with. If the promise did not reject with an `Exception` or `Throwable`, then
this function will throw an `UnexpectedValueException` instead.

```php
try {
    $result = React\Async\await($promise);
    // promise successfully fulfilled with $result
    echo 'Result: ' . $result;
} catch (Throwable $e) {
    // promise rejected with $e
    echo 'Error: ' . $e->getMessage();
}
```

### coroutine()

The `coroutine(callable $function, mixed ...$args): PromiseInterface<mixed>` function can be used to
execute a Generator-based coroutine to "await" promises.

```php
React\Async\coroutine(function () {
    $browser = new React\Http\Browser();

    try {
        $response = yield $browser->get('https://example.com/');
        assert($response instanceof Psr\Http\Message\ResponseInterface);
        echo $response->getBody();
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    }
});
```

Using Generator-based coroutines is an alternative to directly using the
underlying promise APIs. For many use cases, this makes using promise-based
APIs much simpler, as it resembles a synchronous code flow more closely.
The above example performs the equivalent of directly using the promise APIs:

```php
$browser = new React\Http\Browser();

$browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    echo $response->getBody();
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

The `yield` keyword can be used to "await" a promise resolution. Internally,
it will turn the entire given `$function` into a [`Generator`](https://www.php.net/manual/en/class.generator.php).
This allows the execution to be interrupted and resumed at the same place
when the promise is fulfilled. The `yield` statement returns whatever the
promise is fulfilled with. If the promise is rejected, it will throw an
`Exception` or `Throwable`.

The `coroutine()` function will always return a Proimise which will be
fulfilled with whatever your `$function` returns. Likewise, it will return
a promise that will be rejected if you throw an `Exception` or `Throwable`
from your `$function`. This allows you easily create Promise-based functions:

```php
$promise = React\Async\coroutine(function () {
    $browser = new React\Http\Browser();
    $urls = [
        'https://example.com/alice',
        'https://example.com/bob'
    ];

    $bytes = 0;
    foreach ($urls as $url) {
        $response = yield $browser->get($url);
        assert($response instanceof Psr\Http\Message\ResponseInterface);
        $bytes += $response->getBody()->getSize();
    }
    return $bytes;
});

$promise->then(function (int $bytes) {
    echo 'Total size: ' . $bytes . PHP_EOL;
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

The previous example uses a `yield` statement inside a loop to highlight how
this vastly simplifies consuming asynchronous operations. At the same time,
this naive example does not leverage concurrent execution, as it will
essentially "await" between each operation. In order to take advantage of
concurrent execution within the given `$function`, you can "await" multiple
promises by using a single `yield` together with Promise-based primitives
like this:

```php
$promise = React\Async\coroutine(function () {
    $browser = new React\Http\Browser();
    $urls = [
        'https://example.com/alice',
        'https://example.com/bob'
    ];

    $promises = [];
    foreach ($urls as $url) {
        $promises[] = $browser->get($url);
    }

    try {
        $responses = yield React\Promise\all($promises);
    } catch (Exception $e) {
        foreach ($promises as $promise) {
            $promise->cancel();
        }
        throw $e;
    }

    $bytes = 0;
    foreach ($responses as $response) {
        assert($response instanceof Psr\Http\Message\ResponseInterface);
        $bytes += $response->getBody()->getSize();
    }
    return $bytes;
});

$promise->then(function (int $bytes) {
    echo 'Total size: ' . $bytes . PHP_EOL;
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

### parallel()

The `parallel(array<callable():PromiseInterface<mixed,Exception>> $tasks): PromiseInterface<array<mixed>,Exception>` function can be used
like this:

```php
<?php

use React\EventLoop\Loop;
use React\Promise\Promise;

React\Async\parallel([
    function () {
        return new Promise(function ($resolve) {
            Loop::addTimer(1, function () use ($resolve) {
                $resolve('Slept for a whole second');
            });
        });
    },
    function () {
        return new Promise(function ($resolve) {
            Loop::addTimer(1, function () use ($resolve) {
                $resolve('Slept for another whole second');
            });
        });
    },
    function () {
        return new Promise(function ($resolve) {
            Loop::addTimer(1, function () use ($resolve) {
                $resolve('Slept for yet another whole second');
            });
        });
    },
])->then(function (array $results) {
    foreach ($results as $result) {
        var_dump($result);
    }
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

### series()

The `series(array<callable():PromiseInterface<mixed,Exception>> $tasks): PromiseInterface<array<mixed>,Exception>` function can be used
like this:

```php
<?php

use React\EventLoop\Loop;
use React\Promise\Promise;

React\Async\series([
    function () {
        return new Promise(function ($resolve) {
            Loop::addTimer(1, function () use ($resolve) {
                $resolve('Slept for a whole second');
            });
        });
    },
    function () {
        return new Promise(function ($resolve) {
            Loop::addTimer(1, function () use ($resolve) {
                $resolve('Slept for another whole second');
            });
        });
    },
    function () {
        return new Promise(function ($resolve) {
            Loop::addTimer(1, function () use ($resolve) {
                $resolve('Slept for yet another whole second');
            });
        });
    },
])->then(function (array $results) {
    foreach ($results as $result) {
        var_dump($result);
    }
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

### waterfall()

The `waterfall(array<callable(mixed=):PromiseInterface<mixed,Exception>> $tasks): PromiseInterface<mixed,Exception>` function can be used
like this:

```php
<?php

use React\EventLoop\Loop;
use React\Promise\Promise;

$addOne = function ($prev = 0) {
    return new Promise(function ($resolve) use ($prev) {
        Loop::addTimer(1, function () use ($prev, $resolve) {
            $resolve($prev + 1);
        });
    });
};

React\Async\waterfall([
    $addOne,
    $addOne,
    $addOne
])->then(function ($prev) {
    echo "Final result is $prev\n";
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

## Todo

 * Implement queue()

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org/).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

Once released, this project will follow [SemVer](https://semver.org/).
At the moment, this will install the latest development version:

```bash
$ composer require react/async:dev-main
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on PHP 8.1+.
It's *highly recommended to use the latest supported PHP version* for this project.

We're committed to providing long-term support (LTS) options and to provide a
smooth upgrade path. If you're using an older PHP version, you may use the
[`3.x` branch](https://github.com/reactphp/async/tree/3.x) (PHP 7.1+) or
[`2.x` branch](https://github.com/reactphp/async/tree/2.x) (PHP 5.3+) which both
provide a compatible API but do not take advantage of newer language features.
You may target multiple versions at the same time to support a wider range of
PHP versions.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org/):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

MIT, see [LICENSE file](LICENSE).

This project is heavily influenced by [async.js](https://github.com/caolan/async).
