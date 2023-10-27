<?php

use React\Promise\PromiseInterface;
use function PHPStan\Testing\assertType;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\resolve;

assertType('bool', await(resolve(true)));
assertType('bool', await(async(static fn (): bool => true)()));
assertType('bool', await(async(static fn (): PromiseInterface => resolve(true))()));
assertType('bool', await(async(static fn (): bool => await(resolve(true)))()));

final class AwaitExampleUser
{
    public string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }
}

assertType('string', await(resolve(new AwaitExampleUser('WyriHaximus')))->name);
