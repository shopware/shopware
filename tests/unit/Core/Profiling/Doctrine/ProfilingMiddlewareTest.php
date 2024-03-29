<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;

/**
 * @internal
 */
#[CoversClass(ProfilingMiddleware::class)]
class ProfilingMiddlewareTest extends TestCase
{
    public function testData(): void
    {
        $configuration = new Configuration();
        $debugDataHolder = new BacktraceDebugDataHolder(['default']);
        $middleware = new ProfilingMiddleware($debugDataHolder);
        $configuration->setMiddlewares([$middleware]);

        $conn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $configuration);

        $conn->executeQuery(
            <<<EOT
CREATE TABLE products (
	id INTEGER PRIMARY KEY,
	name TEXT NOT NULL,
	price REAL NOT NULL,
	stock INTEGER NOT NULL
);
EOT
        );

        $data = $debugDataHolder->getData();
        static::assertCount(1, $data['default'] ?? []);

        $current = $data['default'][0];

        static::assertSame(0, strpos($current['sql'], 'CREATE TABLE products'));
        static::assertSame([], $current['params']);
        static::assertSame([], $current['types']);
        static::assertGreaterThan(0, $current['executionMS']);
        static::assertSame(Connection::class, $current['backtrace'][0]['class'] ?? '');
        static::assertSame('executeQuery', $current['backtrace'][0]['function'] ?? '');

        $debugDataHolder->reset();
        $data = $debugDataHolder->getData();
        static::assertCount(0, $data['default'] ?? []);
        static::assertSame($debugDataHolder, $middleware->debugDataHolder);
    }
}
