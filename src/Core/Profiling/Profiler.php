<?php

namespace Shopware\Core\Profiling;

use Shopware\Core\Profiling\Integration\ProfilerInterface;

/**
 * @internal experimental atm
 */
abstract class Profiler
{
    /**
     * Profilers will be activated over the shopware.yaml file
     *
     * All enabled profilers will be added here
     *
     * @var ProfilerInterface[]
     */
    private static array $profilers = [];

    public static function trace(string $name, \Closure $closure, string $category = 'shopware')
    {
        $pointer = static function() use ($closure) {
            return $closure();
        };

        // we have to chain the profilers here: `return Stopwatch::trace(Tideways::trace(...));`
        foreach (self::$profilers as $profiler) {
            $pointer = static function() use ($profiler, $name, $pointer, $category) {
                return $profiler->trace($name, $pointer, $category);
            };
        }

        return $pointer();
    }

    public static function register(ProfilerInterface $profiler): void
    {
        self::$profilers[] = $profiler;
    }
}
