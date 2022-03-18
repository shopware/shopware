<?php

namespace Shopware\Core\Profiling\Integration;

/**
 * @internal experimental atm
 */
class Stopwatch implements ProfilerInterface
{
    public static ?\Symfony\Component\Stopwatch\Stopwatch $stopwatch = null;

    public function __construct(\Symfony\Component\Stopwatch\Stopwatch $stopwatch)
    {
        self::$stopwatch = $stopwatch;
    }

    public static function trace(string $title, \Closure $closure, string $category = 'shopware')
    {
        if (!class_exists('\Symfony\Component\Stopwatch\Stopwatch')) {
            return $closure();
        }

        if (self::$stopwatch === null) {
            return $closure();
        }

        self::$stopwatch->start($title, $category);

        $result = $closure();

        self::$stopwatch->stop($title);

        return $result;
    }
}