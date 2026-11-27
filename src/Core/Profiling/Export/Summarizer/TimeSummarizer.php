<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * @internal
 */
#[Package('framework')]
class TimeSummarizer implements PanelSummarizer
{
    private const MAX_EVENTS = 20;

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof TimeDataCollector;
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): array
    {
        \assert($collector instanceof TimeDataCollector);

        $events = array_filter(
            $collector->getEvents(),
            static fn (mixed $event, int|string $name): bool => $event instanceof StopwatchEvent && !str_starts_with((string) $name, '__section__'),
            \ARRAY_FILTER_USE_BOTH,
        );

        uasort($events, static fn (StopwatchEvent $a, StopwatchEvent $b): int => $b->getDuration() <=> $a->getDuration());

        $longest = [];
        foreach (\array_slice($events, 0, self::MAX_EVENTS, true) as $name => $event) {
            $longest[] = [
                'name' => (string) $name,
                'category' => $event->getCategory(),
                'startMs' => round($event->getStartTime(), 1),
                'durationMs' => round($event->getDuration(), 1),
                'memoryMb' => round($event->getMemory() / 1024 / 1024, 1),
            ];
        }

        return [
            'durationMs' => round($collector->getDuration(), 1),
            'initMs' => round($collector->getInitTime(), 1),
            'eventCount' => \count($events),
            'longestEvents' => $longest,
        ];
    }
}
