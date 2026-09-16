<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * Lightweight DBAL driver middleware that counts every executed SQL statement into a shared
 * {@see QueryCounter}. Registered on the `default` connection in {@see KernelFactory}.
 * It only increments an integer, so performance influence is negligible.
 *
 * @internal
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
final class QueryCountMiddleware implements Middleware
{
    private readonly QueryCounter $counter;

    public function __construct(?QueryCounter $counter = null)
    {
        $this->counter = $counter ?? new QueryCounter();
    }

    public function wrap(Driver $driver): Driver
    {
        return new QueryCountDriver($driver, $this->counter);
    }

    public function getCounter(): QueryCounter
    {
        return $this->counter;
    }
}
