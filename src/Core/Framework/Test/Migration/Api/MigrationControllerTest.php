<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\Api\MigrationController;
use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Test\Migration\MigrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class MigrationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use MigrationTestBehaviour;

    protected function tearDown(): void
    {
        $connection = $this->getConnection();

        $connection->createQueryBuilder()
            ->delete('migration')
            ->where('`class` LIKE "%_test_migrations_valid%"')
            ->execute();
    }

    public function getController(): MigrationController
    {
        return $this->getContainer()->get(MigrationController::class);
    }

    public function testAddMigrationsActionCall(): void
    {
        $client = $this->getBrowser();

        $url = '/api/_action/database/sync-migration';

        $client->request('POST', $url, ['identifier' => self::INTEGRATION_IDENTIFIER()]);

        static::assertSame(204, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
    }

    public function testMigrateActionCall(): void
    {
        $client = $this->getBrowser();

        $client->request(
            'POST',
            '/api/_action/database/migrate',
            ['until' => \PHP_INT_MAX]
        );

        static::assertSame(204, $client->getResponse()->getStatusCode());
    }

    public function testMigrateDestructiveActionCall(): void
    {
        $client = $this->getBrowser();

        $client->request(
            'POST',
            '/api/_action/database/migrate-destructive',
            ['until' => \PHP_INT_MAX]
        );

        static::assertSame(204, $client->getResponse()->getStatusCode());
    }

    public function testControllerAddMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $controller = $this->getController();

        $controller->syncMigrations($this->createBasRequest());

        static::assertSame(2, $this->getMigrationCount());
    }

    public function testControllerMigrateMigrationException(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $controller = $this->getController();

        $controller->syncMigrations($this->createBasRequest(true));

        try {
            $controller->migrate($this->createBasRequest(true));
        } catch (MigrateException $e) {
            //nth
        }

        static::assertSame(3, $this->getMigrationCount(true));
    }

    public function testControllerMigrateMigrationDestructive(): void
    {
        static::assertSame(0, $this->getMigrationCount(true, true));

        $controller = $this->getController();

        $controller->syncMigrations($this->createBasRequest(true));

        try {
            $controller->migrate($this->createBasRequest(true));
        } catch (MigrateException $e) {
            //nth
        }

        try {
            $controller->migrateDestructive($this->createBasRequest(true));
        } catch (MigrateException $e) {
            //nth
        }

        static::assertSame(2, $this->getMigrationCount(true, true));
    }

    public function testControllerMigrate(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $controller = $this->getController();

        $controller->syncMigrations($this->createBasRequest());

        $controller->migrate($this->createBasRequest());

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

    private function createBasRequest(bool $exceptions = false): Request
    {
        $identifier = self::INTEGRATION_IDENTIFIER();

        if ($exceptions) {
            $identifier = self::INTEGRATION_WITH_EXCEPTION_IDENTIFIER();
        }

        return new Request([], ['identifier' => $identifier]);
    }
}
