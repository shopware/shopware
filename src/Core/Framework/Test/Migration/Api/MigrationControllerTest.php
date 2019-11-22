<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\Api\MigrationController;
use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class MigrationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    private const MIGRATION_IDENTIFIER = 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid';

    protected function tearDown(): void
    {
        $connection = $this->getConnection();

        $connection->createQueryBuilder()
            ->delete('migration')
            ->where('`class` LIKE "%_test_migrations_valid%"')
            ->execute();
    }

    public function getController(bool $exceptions = false): MigrationController
    {
        $container = $this->getContainer();

        $directories = $container->getParameter('migration.directories');

        $directories['Shopware\Core\Framework\Test\Migration\_test_migrations_valid']
            = __DIR__ . '/../../Migration/_test_migrations_valid';

        if ($exceptions) {
            $directories['Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions']
            = __DIR__ . '/../../Migration/_test_migrations_valid_run_time_exceptions';
        }

        return new MigrationController(
            new MigrationCollectionLoader($this->getConnection(), new MigrationCollection($directories)),
            $container->get(MigrationRuntime::class)
        );
    }

    public function testAddMigrationsActionCall(): void
    {
        $client = $this->getBrowser();

        $url = sprintf('/api/v%s/_action/database/sync-migration', PlatformRequest::API_VERSION);

        $client->request('POST', $url, ['identifier' => self::MIGRATION_IDENTIFIER]);

        static::assertSame(204, $client->getResponse()->getStatusCode());
    }

    public function testMigrateActionCall(): void
    {
        $client = $this->getBrowser();

        $client->request(
            'POST',
            '/api/v' . PlatformRequest::API_VERSION . '/_action/database/migrate',
            ['until' => PHP_INT_MAX]
        );

        static::assertSame(204, $client->getResponse()->getStatusCode());
    }

    public function testMigrateDestructiveActionCall(): void
    {
        $client = $this->getBrowser();

        $client->request(
            'POST',
            '/api/v' . PlatformRequest::API_VERSION . '/_action/database/migrate-destructive',
            ['until' => PHP_INT_MAX]
        );

        static::assertSame(204, $client->getResponse()->getStatusCode());
    }

    public function testControllerAddMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $controller = $this->getController();

        $controller->syncMigrations($this->getBaseRequest());

        static::assertSame(2, $this->getMigrationCount());
    }

    public function testControllerMigrateMigrationException(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $controller = $this->getController(true);

        $controller->syncMigrations($this->getBaseRequest());

        $request = new Request();

        try {
            $controller->migrate($request);
        } catch (MigrateException $e) {
            //nth
        }

        static::assertSame(3, $this->getMigrationCount(true));
    }

    public function testControllerMigrateMigrationDestructive(): void
    {
        static::assertSame(0, $this->getMigrationCount(true, true));

        $controller = $this->getController(true);

        $controller->syncMigrations($this->getBaseRequest());

        $request = new Request();

        try {
            $controller->migrate($request);
        } catch (MigrateException $e) {
            //nth
        }

        try {
            $controller->migrateDestructive($request);
        } catch (MigrateException $e) {
            //nth
        }

        static::assertSame(2, $this->getMigrationCount(true, true));
    }

    public function testControllerMigrate(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $controller = $this->getController();

        $controller->syncMigrations($this->getBaseRequest());

        $request = new Request();

        $controller->migrate($request);

        static::assertSame(2, $this->getMigrationCount(true));
    }

    private function getConnection(): Connection
    {
        return $this->getContainer()->get(Connection::class);
    }

    private function getMigrationCount(bool $executed = false, bool $destructive = false): int
    {
        $connection = $this->getConnection();

        $query = $connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('migration')
            ->where('`class` LIKE "%_test_migrations_valid%"');

        if ($executed && $destructive) {
            $query->andWhere('`update_destructive` IS NOT NULL');
        } elseif ($executed && !$destructive) {
            $query->andWhere('`update` IS NOT NULL');
        }

        return (int) $query->execute()->fetchColumn();
    }

    private function getBaseRequest(): Request
    {
        return new Request([], ['identifier' => self::MIGRATION_IDENTIFIER]);
    }
}
