<?php

namespace React\Tests\Async;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function createCallableMock($expects, $with = null)
    {
        $callable = $this->getMock('React\Tests\Async\CallableStub');

        $method = $callable
            ->expects($expects)
            ->method('__invoke');

        if ($with) {
            $method->with($with);
        }

        return $callable;
    }
}

class CallableStub
{
    public function __invoke()
    {
    }
}
