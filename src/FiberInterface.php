<?php

namespace React\Async;

/**
 * This interface its only purpose is interoperability. Where with
 * event loops one could simply wrap another event loop. But with fibers
 * that has become impossible and as such we provide this interface and the
 * FiberFactory.
 *
 * Usage is not documented and as such not supported and might chang without
 * notice. Use at your own risk.
 *
 * @internal
 */
interface FiberInterface
{
    public function resume(mixed $value): void;

    public function throw(mixed $throwable): void;

    public function suspend(): mixed;
}
