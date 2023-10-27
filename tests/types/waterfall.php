<?php

use React\Promise\PromiseInterface;
use function PHPStan\Testing\assertType;
use function React\Async\await;
use function React\Async\waterfall;
use function React\Promise\resolve;

assertType('React\Promise\PromiseInterface<null>', waterfall([]));

assertType('React\Promise\PromiseInterface<float>', waterfall([
    static fn (): PromiseInterface => resolve(microtime(true)),
]));

assertType('React\Promise\PromiseInterface<float>', waterfall([
    static fn (): float => microtime(true),
]));

// Desired, but currently unsupported with the current set of templates
//assertType('React\Promise\PromiseInterface<float>', waterfall([
//    static fn (): PromiseInterface => resolve(true),
//    static fn (bool $bool): PromiseInterface => resolve(time()),
//    static fn (int $int): PromiseInterface => resolve(microtime(true)),
//]));

assertType('float', await(waterfall([
    static fn (): PromiseInterface => resolve(microtime(true)),
])));

// Desired, but currently unsupported with the current set of templates
//assertType('float', await(waterfall([
//    static fn (): PromiseInterface => resolve(true),
//    static fn (bool $bool): PromiseInterface => resolve(time()),
//    static fn (int $int): PromiseInterface => resolve(microtime(true)),
//])));

// assertType('React\Promise\PromiseInterface<null>', waterfall(new EmptyIterator()));

$iterator = new ArrayIterator([
    static fn (): PromiseInterface => resolve(true),
]);
assertType('React\Promise\PromiseInterface<bool>', waterfall($iterator));
