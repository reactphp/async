<?php

namespace React\Async;

use React\Promise\PromiseInterface;

/**
 * @internal
 */
final class FiberMap
{
    private static array $status = [];
    private static array $map   = [];

    public static function register(\Fiber $fiber): void
    {
        self::$status[\spl_object_id($fiber)] = false;
        self::$map[\spl_object_id($fiber)] = [];
    }

    public static function cancel(\Fiber $fiber): void
    {
        self::$status[\spl_object_id($fiber)] = true;
    }

    public static function isCancelled(\Fiber $fiber): bool
    {
        return self::$status[\spl_object_id($fiber)] ?? false;
    }

    public static function setPromise(\Fiber $fiber, PromiseInterface $promise): void
    {
        self::$map[\spl_object_id($fiber)] = $promise;
    }

    public static function unsetPromise(\Fiber $fiber, PromiseInterface $promise): void
    {
        unset(self::$map[\spl_object_id($fiber)]);
    }

    public static function has(\Fiber $fiber): bool
    {
        return array_key_exists(\spl_object_id($fiber), self::$map);
    }

    public static function getPromise(\Fiber $fiber): ?PromiseInterface
    {
        return self::$map[\spl_object_id($fiber)] ?? null;
    }

    public static function unregister(\Fiber $fiber): void
    {
        unset(self::$status[\spl_object_id($fiber)], self::$map[\spl_object_id($fiber)]);
    }
}
