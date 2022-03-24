<?php

use React\Promise\PromiseInterface;
use function PHPStan\Testing\assertType;
use function React\Async\await;
use function React\Async\series;
use function React\Promise\resolve;

assertType('React\Promise\PromiseInterface<array>', series([]));

assertType('React\Promise\PromiseInterface<array<bool|float|int>>', series([
    static function (): PromiseInterface { return resolve(true); },
    static function (): PromiseInterface { return resolve(time()); },
    static function (): PromiseInterface { return resolve(microtime(true)); },
]));

assertType('React\Promise\PromiseInterface<array<bool|float|int>>', series([
    static function (): bool { return true; },
    static function (): int { return time(); },
    static function (): float { return microtime(true); },
]));

assertType('array<bool|float|int>', await(series([
    static function (): PromiseInterface { return resolve(true); },
    static function (): PromiseInterface { return resolve(time()); },
    static function (): PromiseInterface { return resolve(microtime(true)); },
])));

assertType('array<bool|float|int>', await(series([
    static function (): bool { return true; },
    static function (): int { return time(); },
    static function (): float { return microtime(true); },
])));
