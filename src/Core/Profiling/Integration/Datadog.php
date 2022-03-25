<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

use DDTrace\GlobalTracer;

/**
 * @internal experimental atm
 */
class Datadog implements ProfilerInterface
{
    /**
     * @return mixed
     */
    public function trace(string $title, \Closure $closure, string $category, array $tags)
    {
        if (!class_exists(GlobalTracer::class)) {
            return $closure();
        }
        /** @see \DDTrace\Tag::SERVICE_NAME */
        $tags = array_merge(['service.name' => $category], $tags);
        $span = GlobalTracer::get()->startSpan($title, [
            'tags' => $tags,
        ]);

        $result = $closure();

        $span->finish();

        return $result;
    }
}
