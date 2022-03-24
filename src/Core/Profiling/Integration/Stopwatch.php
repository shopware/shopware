<?php

namespace Shopware\Core\Profiling\Integration;

use Symfony\Component\Stopwatch\Stopwatch as SymfonyStopwatch;

/**
 * @internal experimental atm
 */
class Stopwatch implements ProfilerInterface
{
    private ?SymfonyStopwatch $stopwatch;

    public function __construct(?SymfonyStopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    public function trace(string $title, \Closure $closure, string $category = 'shopware')
    {
        if (!class_exists('\Symfony\Component\Stopwatch\Stopwatch') || $this->stopwatch === null) {
            return $closure();
        }

        $this->stopwatch->start($title, $category);

        $result = $closure();

        $this->stopwatch->stop($title);

        return $result;
    }
}
