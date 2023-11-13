<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

use DDTrace\GlobalTracer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal experimental atm
 */
#[Package('core')]
class Datadog implements ProfilerInterface
{
    private array $spans = [];

    public function start(string $title, string $category, array $tags): void
    {
        if (!class_exists(GlobalTracer::class)) {
            return;
        }

        if ($category !== 'shopware') {
            $category = 'shopware.' . $category;
        }

        /** @see \DDTrace\Tag::SERVICE_NAME */
        $tags = array_merge(['service.name' => $category], $tags);
        $span = GlobalTracer::get()->startActiveSpan($title, [
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
            $span->close();
            unset($this->spans[$title]);
        }
    }
}
