<?php

use React\Promise\PromiseInterface;
use function PHPStan\Testing\assertType;
use function React\Async\await;
use function React\Async\waterfall;
use function React\Promise\resolve;

assertType('React\Promise\PromiseInterface<null>', waterfall([]));

assertType('React\Promise\PromiseInterface<float>', waterfall([
    static function (): PromiseInterface { return resolve(microtime(true)); },
]));

assertType('React\Promise\PromiseInterface<float>', waterfall([
    static function (): float { return microtime(true); },
]));

// Desired, but currently unsupported with the current set of templates
//assertType('React\Promise\PromiseInterface<float>', waterfall([
//    static function (): PromiseInterface { return resolve(true); },
//    static function (bool $bool): PromiseInterface { return resolve(time()); },
//    static function (int $int): PromiseInterface { return resolve(microtime(true)); },
//]));

assertType('float', await(waterfall([
    static function (): PromiseInterface { return resolve(microtime(true)); },
])));

// Desired, but currently unsupported with the current set of templates
//assertType('float', await(waterfall([
//    static function (): PromiseInterface { return resolve(true); },
//    static function (bool $bool): PromiseInterface { return resolve(time()); },
//    static function (int $int): PromiseInterface { return resolve(microtime(true)); },
//])));

// assertType('React\Promise\PromiseInterface<null>', waterfall(new EmptyIterator()));

$iterator = new ArrayIterator([
    static function (): PromiseInterface { return resolve(true); },
]);
assertType('React\Promise\PromiseInterface<bool>', waterfall($iterator));
