<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\MigrationController;
use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class MigrationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function tearDown()
    {
        $connection = $this->getConnection();

        $connection->createQueryBuilder()
            ->delete('migration')
            ->where('`class` LIKE "%_test_migrations_valid%"')
            ->execute();
    }

    public function getController(bool $exceptions = false): MigrationController
    {
        $container = self::getKernel()->getContainer();

        $directories = $container->getParameter('migration.directories');

        $directories['Shopware\Core\Framework\Test\Migration\_test_migrations_valid'] =
            __DIR__ . '/../../Migration/_test_migrations_valid';

        if ($exceptions) {
            $directories['Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions'] =
            __DIR__ . '/../../Migration/_test_migrations_valid_run_time_exceptions';
        }

        return new MigrationController(
            new MigrationCollectionLoader($this->getConnection()),
            $container->get(MigrationRuntime::class),
            $directories
        );
    }

    public function test_add_migrations_action_call()
    {
        $client = $this->getClient();

        $client->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/migration/add');

        self::assertSame(json_encode(['message' => 'migrations added to the database']), $client->getResponse()->getContent());
    }

    public function test_migrate_action_call()
    {
        $client = $this->getClient();

        $client->request(
            'POST',
            '/api/v' . PlatformRequest::API_VERSION . '/migration/migrate',
            ['until' => PHP_INT_MAX]
        );

        self::assertSame(json_encode(['message' => 'Migrations executed']), $client->getResponse()->getContent());
    }

    public function test_migrate_destructive_action_call()
    {
        $client = $this->getClient();

        $client->request(
            'POST',
            '/api/v' . PlatformRequest::API_VERSION . '/migration/migrate-destructive',
            ['until' => PHP_INT_MAX]
        );

        self::assertSame(json_encode(['message' => 'Migrations executed']), $client->getResponse()->getContent());
    }

    public function test_controller_add_Migrations()
    {
        self::assertSame(0, $this->getMigrationCount());

        $controller = $this->getController();

        $controller->addMigrations();

        self::assertSame(2, $this->getMigrationCount());
    }

    public function test_controller_migrate_migration_exception()
    {
        self::assertSame(0, $this->getMigrationCount(true));

        $controller = $this->getController(true);

        $controller->addMigrations();

        $request = new Request();

        try {
            $controller->migrate($request);
        } catch (MigrateException $e) {
            //nth
        }

        self::assertSame(3, $this->getMigrationCount(true));
    }

    public function test_controller_migrate_migration_destructive()
    {
        self::assertSame(0, $this->getMigrationCount(true, true));

        $controller = $this->getController(true);

        $controller->addMigrations();

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

        self::assertSame(2, $this->getMigrationCount(true, true));
    }

    public function test_controller_migrate()
    {
        self::assertSame(0, $this->getMigrationCount(true));

        $controller = $this->getController();

        $controller->addMigrations();

        $request = new Request();

        $controller->migrate($request);

        self::assertSame(2, $this->getMigrationCount(true));
    }

    private function getConnection(): Connection
    {
        return self::getKernel()->getContainer()->get(Connection::class);
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
}
