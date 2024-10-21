<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Stopwatch\Stopwatch as SymfonyStopwatch;

/**
 * @internal
 */
#[Package('core')]
class ServerTiming implements ProfilerInterface
{
    private SymfonyStopwatch $watch;

    /**
     * @var array<string>
     */
    private array $elements = [];

    public function __construct()
    {
        $this->watch = new SymfonyStopwatch();
    }

    /**
     * @param array<string> $tags
     */
    public function start(string $title, string $category, array $tags): void
    {
        $this->watch->start($title, $category);
    }

    public function stop(string $title): void
    {
        $this->watch->stop($title);

        $stopwatchEvent = $this->watch->getEvent($title);

        if ($stopwatchEvent->getDuration() === 0) {
            return;
        }

        $this->elements[] = \sprintf('%s;dur=%d', str_replace('::', '.', $title), $stopwatchEvent->getDuration());
    }

    public function onResponseEvent(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        $response->headers->set('Server-Timing', implode(', ', $this->elements));
        $this->elements = [];
        $this->watch->reset();
    }
}
