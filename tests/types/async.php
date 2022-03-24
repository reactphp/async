<?php

use React\Promise\PromiseInterface;
use function PHPStan\Testing\assertType;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\resolve;

assertType('React\Promise\PromiseInterface<bool>', async(static fn (): bool => true)());
assertType('React\Promise\PromiseInterface<bool>', async(static fn (): PromiseInterface => resolve(true))());
assertType('React\Promise\PromiseInterface<bool>', async(static fn (): bool => await(resolve(true)))());

assertType('React\Promise\PromiseInterface<int>', async(static fn (int $a): int => $a)(42));
assertType('React\Promise\PromiseInterface<int>', async(static fn (int $a, int $b): int => $a + $b)(10, 32));
assertType('React\Promise\PromiseInterface<int>', async(static fn (int $a, int $b, int $c): int => $a + $b + $c)(10, 22, 10));
assertType('React\Promise\PromiseInterface<int>', async(static fn (int $a, int $b, int $c, int $d): int => $a + $b + $c + $d)(10, 22, 5, 5));
assertType('React\Promise\PromiseInterface<int>', async(static fn (int $a, int $b, int $c, int $d, int $e): int => $a + $b + $c + $d + $e)(10, 12, 10, 5, 5));
