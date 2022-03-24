<?php

use function PHPStan\Testing\assertType;
use function React\Async\await;
use function React\Async\coroutine;
use function React\Promise\resolve;

assertType('React\Promise\PromiseInterface<bool>', coroutine(static function () {
    return true;
}));

assertType('React\Promise\PromiseInterface<bool>', coroutine(static function () {
    return resolve(true);
}));

// assertType('React\Promise\PromiseInterface<bool>', coroutine(static function () {
//     return (yield resolve(true));
// }));

assertType('React\Promise\PromiseInterface<int>', coroutine(static function () {
//     $bool = yield resolve(true);
//     assertType('bool', $bool);

    return time();
}));

// assertType('React\Promise\PromiseInterface<bool>', coroutine(static function () {
//     $bool = yield resolve(true);
//     assertType('bool', $bool);

//     return $bool;
// }));

assertType('React\Promise\PromiseInterface<bool>', coroutine(static function () {
    yield resolve(time());

    return true;
}));

assertType('React\Promise\PromiseInterface<bool>', coroutine(static function () {
    for ($i = 0; $i <= 10; $i++) {
        yield resolve($i);
    }

    return true;
}));

assertType('React\Promise\PromiseInterface<int>', coroutine(static function (int $a): int { return $a; }, 42));
assertType('React\Promise\PromiseInterface<int>', coroutine(static function (int $a, int $b): int { return $a + $b; }, 10, 32));
assertType('React\Promise\PromiseInterface<int>', coroutine(static function (int $a, int $b, int $c): int { return $a + $b + $c; }, 10, 22, 10));
assertType('React\Promise\PromiseInterface<int>', coroutine(static function (int $a, int $b, int $c, int $d): int { return $a + $b + $c + $d; }, 10, 22, 5, 5));
assertType('React\Promise\PromiseInterface<int>', coroutine(static function (int $a, int $b, int $c, int $d, int $e): int { return $a + $b + $c + $d + $e; }, 10, 12, 10, 5, 5));

assertType('bool', await(coroutine(static function () {
    return true;
})));

// assertType('bool', await(coroutine(static function () {
//     return (yield resolve(true));
// })));
