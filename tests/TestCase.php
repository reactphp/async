<?php

namespace React\Tests\Async;

use PHPUnit\Framework\MockObject\MockBuilder;
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

    /** @param mixed $value */
    protected function expectCallableOnceWith($value): callable
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
        if (method_exists(MockBuilder::class, 'addMethods')) {
            // @phpstan-ignore-next-line PHPUnit 9+
            return $this->getMockBuilder(\stdClass::class)->addMethods(['__invoke'])->getMock();
        } else {
            // PHPUnit < 9
            return $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        }
    }
}
