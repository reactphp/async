<?php

namespace React\Async;

use React\Promise\PromiseInterface;

/**
 * @internal
 */
final class FiberMap
{
    private static ?\WeakMap $status = null;
    private static ?\WeakMap $map = null;

    public static function register(\Fiber $fiber): void
    {
        if (self::$status === null) {
            self::$status = new \WeakMap();
        }
        if (self::$map === null) {
            self::$map = new \WeakMap();
        }

        self::$status[$fiber] = false;
        self::$map[$fiber] = [];
    }

    public static function cancel(\Fiber $fiber): void
    {
        self::$status[$fiber] = true;
    }

    public static function isCancelled(\Fiber $fiber): bool
    {
        return self::$status[$fiber] ?? false;
    }

    public static function setPromise(\Fiber $fiber, PromiseInterface $promise): void
    {
        self::$map[$fiber] = $promise;
    }

    public static function unsetPromise(\Fiber $fiber): void
    {
        unset(self::$map[$fiber]);
    }

    public static function has(\Fiber $fiber): bool
    {
        return array_key_exists($fiber, self::$map);
    }

    public static function getPromise(\Fiber $fiber): ?PromiseInterface
    {
        return self::$map[$fiber] ?? null;
    }

    public static function unregister(\Fiber $fiber): void
    {
        unset(self::$status[$fiber], self::$map[$fiber]);
    }
}
