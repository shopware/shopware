<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Subscriber\CacheTagCollectorSubscriber;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * @internal
 */
#[Package('framework')]
class CacheTagSummarizer implements PanelSummarizer
{
    private const MAX_TAGS = 100;

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof CacheTagCollectorSubscriber;
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): array
    {
        \assert($collector instanceof CacheTagCollectorSubscriber);

        $summary = [];
        foreach ($collector->getData() as $uri => $tags) {
            $names = array_map(strval(...), array_keys($tags));
            $remaining = \count($names) - self::MAX_TAGS;
            if ($remaining > 0) {
                $names = [...\array_slice($names, 0, self::MAX_TAGS), \sprintf('[… %d more]', $remaining)];
            }

            $summary[$uri] = ['count' => \count($tags), 'tags' => $names];
        }

        return $summary;
    }
}
