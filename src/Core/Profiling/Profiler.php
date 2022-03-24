<?php declare(strict_types=1);

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

    /**
     * Tags will be added to each trace
     *
     * @var string[]
     */
    private static array $tags = [];

    /**
     * @return mixed
     */
    public static function trace(string $name, \Closure $closure, string $category = 'shopware', array $tags = [])
    {
        $pointer = static function () use ($closure) {
            return $closure();
        };

        $tags = array_merge(self::$tags, $tags);
        // we have to chain the profilers here: `return Stopwatch::trace(Tideways::trace(...));`
        foreach (self::$profilers as $profiler) {
            $pointer = static function () use ($profiler, $name, $pointer, $category, $tags) {
                return $profiler->trace($name, $pointer, $category, $tags);
            };
        }

        return $pointer();
    }

    public static function register(ProfilerInterface $profiler): void
    {
        self::$profilers[] = $profiler;
    }

    public static function addTag(string $key, string $value): void
    {
        self::$tags[$key] = $value;
    }

    public static function removeTag(string $key): void
    {
        unset(self::$tags[$key]);
    }
}
