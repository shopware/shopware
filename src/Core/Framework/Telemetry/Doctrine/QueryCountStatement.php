<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
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
final class QueryCountStatement extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $statement,
        private readonly QueryCounter $counter,
    ) {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        $this->counter->increment();

        return parent::execute();
    }
}
