<?php declare(strict_types=1);

namespace Shopware\Core\Profiling;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Integration\ProfilerInterface;

/**
 * @internal experimental atm
 */
#[Package('core')]
class Profiler
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
     * @var array<string>
     */
    private static array $tags = [];

    /**
     * @var array<string>
     */
    private static array $openTraces = [];

    /**
     * @param array<string> $activeProfilers
     */
    public function __construct(
        \Traversable $profilers,
        array $activeProfilers
    ) {
        $profilers = iterator_to_array($profilers);
        self::$profilers = array_intersect_key($profilers, array_flip($activeProfilers));
        self::$tags = [];

        register_shutdown_function(fn () => self::cleanup());
    }

    /**
     * @return mixed
     */
    public static function trace(string $name, \Closure $closure, string $category = 'shopware', array $tags = [])
    {
        $tags = array_merge(self::$tags, $tags);

        try {
            foreach (self::$profilers as $profiler) {
                $profiler->start($name, $category, $tags);
            }

            $result = $closure();
        } finally {
            foreach (self::$profilers as $profiler) {
                $profiler->stop($name);
            }
        }

        return $result;
    }

    public static function start(string $title, string $category, array $tags): void
    {
        self::$openTraces[] = $title;
        $tags = array_merge(self::$tags, $tags);

        foreach (self::$profilers as $profiler) {
            $profiler->start($title, $category, $tags);
        }
    }

    public static function stop(string $title): void
    {
        foreach (self::$profilers as $profiler) {
            $profiler->stop($title);
        }

        unset(self::$openTraces[$title]);
    }

    public static function cleanup(): void
    {
        foreach (self::$openTraces as $name) {
            foreach (self::$profilers as $profiler) {
                $profiler->stop($name);
            }
        }

        self::$openTraces = [];
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
