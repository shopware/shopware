<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

use DDTrace\GlobalTracer;

/**
 * @internal experimental atm
 */
class Datadog implements ProfilerInterface
{
    private array $spans = [];

    public function start(string $title, string $category, array $tags): void
    {
        if (!class_exists(GlobalTracer::class)) {
            return;
        }

        /** @see \DDTrace\Tag::SERVICE_NAME */
        $tags = array_merge(['service.name' => $category], $tags);
        $span = GlobalTracer::get()->startSpan($title, [
            'tags' => $tags,
        ]);

        $this->spans[$title] = $span;
    }

    public function stop(string $title): void
    {
        if (!class_exists(GlobalTracer::class)) {
            return;
        }

        $span = $this->spans[$title] ?? null;

        if ($span) {
            $span->finish();
            unset($this->spans[$title]);
        }
    }
}
