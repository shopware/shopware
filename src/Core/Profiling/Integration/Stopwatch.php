<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

use Symfony\Component\Stopwatch\Stopwatch as SymfonyStopwatch;

/**
 * @package core
 *
 * @internal experimental atm
 */
class Stopwatch implements ProfilerInterface
{
    private ?SymfonyStopwatch $stopwatch;

    public function __construct(?SymfonyStopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    public function start(string $title, string $category, array $tags): void
    {
        if (!class_exists('\Symfony\Component\Stopwatch\Stopwatch') || $this->stopwatch === null) {
            return;
        }

        $this->stopwatch->start($title, $category);
    }

    public function stop(string $title): void
    {
        if (!class_exists('\Symfony\Component\Stopwatch\Stopwatch') || $this->stopwatch === null) {
            return;
        }

        $this->stopwatch->stop($title);
    }
}
