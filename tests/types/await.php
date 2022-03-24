<?php

use function PHPStan\Testing\assertType;
use function React\Async\await;
use function React\Promise\resolve;

assertType('bool', await(resolve(true)));

final class AwaitExampleUser
{
    public string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }
}

assertType('string', await(resolve(new AwaitExampleUser('WyriHaximus')))->name);
