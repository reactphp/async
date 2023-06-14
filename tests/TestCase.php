<?php

namespace React\Tests\Async;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function expectCallableOnce(): callable
    {
        $mock = $this->createCallableMock();
        $mock->expects($this->once())->method('__invoke');
        assert(is_callable($mock));

        return $mock;
    }

    protected function expectCallableOnceWith(mixed $value): callable
    {
        $mock = $this->createCallableMock();
        $mock->expects($this->once())->method('__invoke')->with($value);
        assert(is_callable($mock));

        return $mock;
    }

    protected function expectCallableNever(): callable
    {
        $mock = $this->createCallableMock();
        $mock->expects($this->never())->method('__invoke');
        assert(is_callable($mock));

        return $mock;
    }

    protected function createCallableMock(): MockObject
    {
        return $this->getMockBuilder(\stdClass::class)->addMethods(['__invoke'])->getMock();
    }
}
