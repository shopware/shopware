<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

use Tideways\Profiler;

/**
 * @package core
 *
 * @internal experimental atm
 */
class Tideways implements ProfilerInterface
{
    private array $spans = [];

    public function start(string $title, string $category, array $tags): void
    {
        if (!class_exists('Tideways\Profiler')) {
            return;
        }

        $tags = array_merge(['title' => $title], $tags);
        $span = Profiler::createSpan($category);
        $span->annotate($tags);
        $this->spans[$title] = $span;
    }

    public function stop(string $title): void
    {
        if (!class_exists('Tideways\Profiler')) {
            return;
        }

        $span = $this->spans[$title] ?? null;

        if ($span) {
            $span->finish();
            unset($this->spans[$title]);
        }
    }
}
