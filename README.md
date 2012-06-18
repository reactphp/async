# React/Async

Async utilities for React.

It is heavily influenced by [async.js](https://github.com/caolan/async).

[![Build Status](https://secure.travis-ci.org/react-php/async.png?branch=master)](http://travis-ci.org/react-php/zmq)

## Install

The recommended way to install react/async is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "react/async": "dev-master"
    }
}
```

## Example

```php
<?php

$loop = React\EventLoop\Factory::create();

React\Async\Util::parallel(
    array(
        function ($callback, $errback) use ($loop) {
            $loop->addTimer(1, function () use ($callback) {
                $callback('Slept for a whole second');
            });
        },
        function ($callback, $errback) use ($loop) {
            $loop->addTimer(1, function () use ($callback) {
                $callback('Slept for another whole second');
            });
        },
        function ($callback, $errback) use ($loop) {
            $loop->addTimer(1, function () use ($callback) {
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

$loop->run();
```

## Todo

 * Implement waterfall()
 * Implement queue()

## Tests

To run the test suite, you need PHPUnit.

    $ phpunit

## License

MIT, see LICENSE.
