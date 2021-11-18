<?php

namespace React\Tests\Async;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceWith($value)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($value);

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function createCallableMock()
    {
        if (method_exists(MockBuilder::class, 'addMethods')) {
            // PHPUnit 9+
            return $this->getMockBuilder(\stdClass::class)->addMethods(['__invoke'])->getMock();
        } else {
            // PHPUnit < 9
            return $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        }
    }
}
