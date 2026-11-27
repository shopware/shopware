<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export\Summarizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\Summarizer\TimeSummarizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TimeSummarizer::class)]
class TimeSummarizerTest extends TestCase
{
    public function testSupportsOnlyTheTimeCollector(): void
    {
        static::assertTrue((new TimeSummarizer())->supports(new TimeDataCollector()));
        static::assertFalse((new TimeSummarizer())->supports(new MemoryDataCollector()));
    }

    public function testSummarizesDurationAndLongestEvents(): void
    {
        $collector = $this->collector([
            '__section__' => $this->event('section', origin: 1_000_050.0, start: 0.0, duration: 200.0),
            '__section__.child' => $this->event('section', origin: 1_000_050.0, start: 0.0, duration: 190.0),
            'kernel.request' => $this->event('section', origin: 1_000_050.0, start: 1.04, duration: 30.0),
            'product-listing-route::loading' => $this->event('saleschannel-repository', origin: 1_000_050.0, start: 40.0, duration: 120.06),
        ]);

        static::assertSame(
            [
                'durationMs' => 250.0,
                'initMs' => 50.0,
                'eventCount' => 2,
                'longestEvents' => [
                    ['name' => 'product-listing-route::loading', 'category' => 'saleschannel-repository', 'startMs' => 40.0, 'durationMs' => 120.1, 'memoryMb' => 4.0],
                    ['name' => 'kernel.request', 'category' => 'section', 'startMs' => 1.0, 'durationMs' => 30.0, 'memoryMb' => 4.0],
                ],
            ],
            (new TimeSummarizer())->summarize($collector, null),
        );
    }

    public function testKeepsTheTwentyLongestEvents(): void
    {
        $events = [];
        for ($i = 1; $i <= 25; ++$i) {
            $events['event-' . $i] = $this->event('app', origin: 1_000_000.0, start: 0.0, duration: (float) $i);
        }

        $summary = (new TimeSummarizer())->summarize($this->collector($events), null);

        static::assertSame(25, $summary['eventCount']);
        static::assertCount(20, $summary['longestEvents']);
        static::assertSame('event-25', $summary['longestEvents'][0]['name']);
        static::assertSame('event-6', $summary['longestEvents'][19]['name']);
    }

    /**
     * @param array<string, StopwatchEvent> $events
     */
    private function collector(array $events): TimeDataCollector
    {
        $request = new Request(server: ['REQUEST_TIME_FLOAT' => 1000.0]);
        $collector = new TimeDataCollector();
        $collector->collect($request, new Response());
        $collector->setEvents($events);

        return $collector;
    }

    private function event(string $category, float $origin, float $start, float $duration): StopwatchEvent
    {
        $event = static::createStub(StopwatchEvent::class);
        $event->method('getCategory')->willReturn($category);
        $event->method('getOrigin')->willReturn($origin);
        $event->method('getStartTime')->willReturn($start);
        $event->method('getDuration')->willReturn($duration);
        $event->method('getMemory')->willReturn(4 * 1024 * 1024);

        return $event;
    }
}
