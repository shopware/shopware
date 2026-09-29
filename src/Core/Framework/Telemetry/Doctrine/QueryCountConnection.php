<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
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
final class QueryCountConnection extends AbstractConnectionMiddleware
{
    public function __construct(
        Connection $connection,
        private readonly QueryCounter $counter,
    ) {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new QueryCountStatement(parent::prepare($sql), $this->counter);
    }

    public function query(string $sql): Result
    {
        $this->counter->increment();

        return parent::query($sql);
    }

    public function exec(string $sql): int|string
    {
        $this->counter->increment();

        return parent::exec($sql);
    }
}
