<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Common;

use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\HRTime;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class TimeKeeper
{
    /**
     * @var array<string, HRTime>
     */
    private array $startedTimes = [];

    public function start(string $testIdentifier, HRTime $startedTime): void
    {
        $this->startedTimes[$testIdentifier] = $startedTime;
    }

    public function stop(string $testIdentifier, HRTime $stoppedTime): Duration
    {
        if (!\array_key_exists($testIdentifier, $this->startedTimes)) {
            return Duration::fromSecondsAndNanoseconds(
                0,
                0,
            );
        }

        $startedTime = $this->startedTimes[$testIdentifier];

        unset($this->startedTimes[$testIdentifier]);

        return $stoppedTime->duration($startedTime);
    }
}
