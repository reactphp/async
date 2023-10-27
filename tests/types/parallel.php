<?php

use React\Promise\PromiseInterface;
use function PHPStan\Testing\assertType;
use function React\Async\await;
use function React\Async\parallel;
use function React\Promise\resolve;

assertType('React\Promise\PromiseInterface<array>', parallel([]));

assertType('React\Promise\PromiseInterface<array<bool|float|int>>', parallel([
    static fn (): PromiseInterface => resolve(true),
    static fn (): PromiseInterface => resolve(time()),
    static fn (): PromiseInterface => resolve(microtime(true)),
]));

assertType('React\Promise\PromiseInterface<array<bool|float|int>>', parallel([
    static fn (): bool => true,
    static fn (): int => time(),
    static fn (): float => microtime(true),
]));

assertType('array<bool|float|int>', await(parallel([
    static fn (): PromiseInterface => resolve(true),
    static fn (): PromiseInterface => resolve(time()),
    static fn (): PromiseInterface => resolve(microtime(true)),
])));

assertType('array<bool|float|int>', await(parallel([
    static fn (): bool => true,
    static fn (): int => time(),
    static fn (): float => microtime(true),
])));
