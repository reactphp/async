# NOTE: This package is no longer maintained. Use [react/promise](https://github.com/reactphp/promise) instead!

# Async

Async utilities for [ReactPHP](https://reactphp.org/).

It is heavily influenced by [async.js](https://github.com/caolan/async).

[![CI status](https://github.com/reactphp/async/workflows/CI/badge.svg)](https://github.com/reactphp/async/actions)

This library allows you to manage async control flow. It provides a number of
combinators for continuation-passing style (aka callbacks). Instead of nesting
those callbacks, you can declare them as a list, which is resolved
sequentially in an async manner.

React/Async will not automagically change blocking code to be async. You need
to have an actual event loop and non-blocking libraries interacting with that
event loop for it to work. You can use `react/event-loop` for this, but you
don't have to. As long as you have a callback-based API that runs in an event
loop, it can be used with this library.

*You must be running inside an event loop for react/async to make any sense
whatsoever!*

**Table of Contents**

* [Usage](#usage)
    * [Parallel](#parallel)
    * [Waterfall](#waterfall)
* [Todo](#todo)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Usage

### Parallel

```php
<?php

use React\Async\Util as Async;
use React\EventLoop\Loop;

Async::parallel(
    array(
        function ($callback, $errback) {
            Loop::addTimer(1, function () use ($callback) {
                $callback('Slept for a whole second');
            });
        },
        function ($callback, $errback) {
            Loop::addTimer(1, function () use ($callback) {
                $callback('Slept for another whole second');
            });
        },
        function ($callback, $errback) {
            Loop::addTimer(1, function () use ($callback) {
                $callback('Slept for yet another whole second');
            });
        },
    ),
    function (array $results) {
        foreach ($results as $result) {
            var_dump($result);
        }
    },
    function (\Exception $e) {
        throw $e;
    }
);
```

### Waterfall

```php
<?php

use React\Async\Util as Async;
use React\EventLoop\Loop;

$addOne = function ($prev, $callback = null) {
    if (!$callback) {
        $callback = $prev;
        $prev = 0;
    }

    Loop::addTimer(1, function () use ($prev, $callback) {
        $callback($prev + 1);
    });
};

Async::waterfall(array(
    $addOne,
    $addOne,
    $addOne,
    function ($prev, $callback) use ($loop) {
        echo "Final result is $prev\n";
        $callback();
    },
));
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
extensions and supports running on legacy PHP 5.3 through current PHP 8+.
It's *highly recommended to use the latest supported PHP version* for this project.

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
