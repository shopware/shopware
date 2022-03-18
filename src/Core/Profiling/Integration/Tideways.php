<?php

namespace Shopware\Core\Profiling\Integration;

/**
 * @internal experimental atm
 */
class Tideways implements ProfilerInterface
{
    public static function trace(string $title, \Closure $closure, string $category = 'shopware')
    {
        if (!class_exists('Tideways\Profiler')) {
            return $closure();
        }

        $span = \Tideways\Profiler::createSpan($category);
        $span->annotate(['title' => $title]);

        $result = $closure();

        $span->finish();

        return $result;
    }
}