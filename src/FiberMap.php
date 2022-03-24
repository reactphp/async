<?php

namespace React\Async;

use React\Promise\PromiseInterface;

/**
 * @internal
 *
 * @template T
 */
final class FiberMap
{
    /** @var array<int,bool> */
    private static array $status = [];

    /** @var array<int,PromiseInterface<T>> */
    private static array $map = [];

    /** @param \Fiber<mixed,mixed,mixed,mixed> $fiber */
    public static function register(\Fiber $fiber): void
    {
        self::$status[\spl_object_id($fiber)] = false;
    }

    /** @param \Fiber<mixed,mixed,mixed,mixed> $fiber */
    public static function cancel(\Fiber $fiber): void
    {
        self::$status[\spl_object_id($fiber)] = true;
    }

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed> $fiber
     * @param PromiseInterface<T> $promise
     */
    public static function setPromise(\Fiber $fiber, PromiseInterface $promise): void
    {
        self::$map[\spl_object_id($fiber)] = $promise;
    }

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed> $fiber
     * @param PromiseInterface<T> $promise
     */
    public static function unsetPromise(\Fiber $fiber, PromiseInterface $promise): void
    {
        unset(self::$map[\spl_object_id($fiber)]);
    }

    /**
     * @param \Fiber<mixed,mixed,mixed,mixed> $fiber
     * @return ?PromiseInterface<T>
     */
    public static function getPromise(\Fiber $fiber): ?PromiseInterface
    {
        return self::$map[\spl_object_id($fiber)] ?? null;
    }

    /** @param \Fiber<mixed,mixed,mixed,mixed> $fiber */
    public static function unregister(\Fiber $fiber): void
    {
        unset(self::$status[\spl_object_id($fiber)], self::$map[\spl_object_id($fiber)]);
    }
}
