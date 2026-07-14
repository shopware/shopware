<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1784053323MigrateAppModulesToAppFeature;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1784053323MigrateAppModulesToAppFeature::class)]
class Migration1784053323MigrateAppModulesToAppFeatureTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1784053323, (new Migration1784053323MigrateAppModulesToAppFeature())->getCreationTimestamp());
    }

    public function testUpdateMovesModulesIntoAppFeature(): void
    {
        $this->ensureColumns();

        // rolled back manually so no DDL-incompatible global transaction is needed
        $this->connection->beginTransaction();

        try {
            $appFixture = new AppFixture(static::getContainer()->get('app.repository'));
            $app = $appFixture->createAppFromData();
            $appId = Uuid::fromHexToBytes($app->getId());

            $modules = [
                ['name' => 'first', 'label' => ['en-GB' => 'First'], 'parent' => 'sw-catalogue', 'source' => 'https://first', 'position' => 50],
                ['name' => 'second', 'label' => ['en-GB' => 'Second'], 'parent' => null, 'source' => null, 'position' => 1],
            ];
            $this->connection->update('app', [
                'modules' => Json::encode($modules),
                'main_module' => Json::encode(['source' => 'https://main']),
            ], ['id' => $appId]);

            (new Migration1784053323MigrateAppModulesToAppFeature())->update($this->connection);

            $rows = $this->connection->fetchAllAssociative(
                'SELECT `name`, `payload` FROM `app_feature` WHERE `app_id` = :id AND `type` = :type',
                ['id' => $appId, 'type' => 'module']
            );

            static::assertCount(1, $rows);
            static::assertSame('admin', $rows[0]['name']);

            $payload = json_decode((string) $rows[0]['payload'], true, flags: \JSON_THROW_ON_ERROR);
            static::assertCount(2, $payload['modules']);
            static::assertSame('first', $payload['modules'][0]['name']);
            static::assertSame(['source' => 'https://main'], $payload['mainModule']);
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testUpdateDestructiveDropsColumnsIdempotently(): void
    {
        $this->ensureColumns();

        $migration = new Migration1784053323MigrateAppModulesToAppFeature();
        $migration->updateDestructive($this->connection);
        // idempotent: a second run must not fail
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'app', 'modules'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'app', 'main_module'));

        // restore for the rest of the suite
        $this->ensureColumns();
    }

    private function ensureColumns(): void
    {
        if (!TableHelper::columnExists($this->connection, 'app', 'modules')) {
            $this->connection->executeStatement(
                'ALTER TABLE `app`
                    ADD COLUMN `modules` JSON NULL,
                    ADD CONSTRAINT `json.app.modules` CHECK (JSON_VALID(`modules`))'
            );
        }

        if (!TableHelper::columnExists($this->connection, 'app', 'main_module')) {
            $this->connection->executeStatement(
                'ALTER TABLE `app`
                    ADD COLUMN `main_module` JSON NULL,
                    ADD CONSTRAINT `json.app.main_module` CHECK (JSON_VALID(`main_module`))'
            );
        }
    }
}
