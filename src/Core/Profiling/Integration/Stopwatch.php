<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Stopwatch\Stopwatch as SymfonyStopwatch;

/**
 * @internal experimental atm
 */
#[Package('core')]
class Stopwatch implements ProfilerInterface
{
    public function __construct(private readonly ?SymfonyStopwatch $stopwatch)
    {
    }

    /**
     * @param array<string> $tags
     */
    public function start(string $title, string $category, array $tags): void
    {
        $this->stopwatch?->start($title, $category);
    }

    public function stop(string $title): void
    {
        if ($this->stopwatch?->isStarted($title)) {
            $this->stopwatch->stop($title);
        }
    }
}
