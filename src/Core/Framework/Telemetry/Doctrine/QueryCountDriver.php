<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Unit\Core\Framework\Telemetry\Doctrine\QueryCountMiddlewareTest
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
final class QueryCountDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly QueryCounter $counter,
    ) {
        parent::__construct($driver);
    }

    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): DriverConnection {
        return new QueryCountConnection(parent::connect($params), $this->counter);
    }
}
