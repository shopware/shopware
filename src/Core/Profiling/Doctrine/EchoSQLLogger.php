<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Doctrine;

use Doctrine\DBAL\Logging\SQLLogger;
use Shopware\Core\Profiling\Twig\DoctrineExtension;

/**
 * Print executed SQL to the console, in such a way that they can be easily copied to other SQL tools for further
 * debugging. This is similar to the symfony debug bar, but useful in CLI commands and tests.
 *
 * Usage in tests:
 *     Kernel::getConnection()->getConfiguration()->setSQLLogger(
 *         new \Shopware\Core\Profiling\Doctrine\EchoSQLLogger()
 *     );
 */
class EchoSQLLogger implements SQLLogger
{
    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $doctrineExtension = new DoctrineExtension();
        echo $doctrineExtension->replaceQueryParameters(
            $sql,
            array_merge($params ?? [], $types ?? [])
        )
            . ';'
            . \PHP_EOL;
    }

    public function stopQuery(): void
    {
    }
}
