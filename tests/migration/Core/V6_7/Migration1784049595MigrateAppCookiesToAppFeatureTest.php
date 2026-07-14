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
use Shopware\Core\Migration\V6_7\Migration1784049595MigrateAppCookiesToAppFeature;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1784049595MigrateAppCookiesToAppFeature::class)]
class Migration1784049595MigrateAppCookiesToAppFeatureTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1784049595, (new Migration1784049595MigrateAppCookiesToAppFeature())->getCreationTimestamp());
    }

    public function testUpdateMovesCookiesIntoAppFeature(): void
    {
        $this->ensureCookiesColumn();

        // rolled back manually so no DDL-incompatible global transaction is needed
        $this->connection->beginTransaction();

        try {
            $appFixture = new AppFixture(static::getContainer()->get('app.repository'));
            $app = $appFixture->createAppFromData();
            $appId = Uuid::fromHexToBytes($app->getId());

            $cookies = [
                [
                    'snippet_name' => 'app.cookies.group',
                    'snippet_description' => 'app.cookies.group.description',
                    'entries' => [
                        ['cookie' => 'swag-app-something', 'snippet_name' => 'first.cookie'],
                    ],
                ],
                ['snippet_name' => 'swag.analytics', 'cookie' => 'swag-analytics', 'value' => '', 'expiration' => '30'],
            ];
            $this->connection->update('app', ['cookies' => Json::encode($cookies)], ['id' => $appId]);

            (new Migration1784049595MigrateAppCookiesToAppFeature())->update($this->connection);

            $rows = $this->connection->fetchAllAssociative(
                'SELECT `name`, `payload` FROM `app_feature` WHERE `app_id` = :id AND `type` = :type ORDER BY `name`',
                ['id' => $appId, 'type' => 'cookie']
            );

            static::assertCount(2, $rows);

            static::assertSame('app.cookies.group', $rows[0]['name']);
            $group = json_decode((string) $rows[0]['payload'], true, flags: \JSON_THROW_ON_ERROR);
            static::assertSame('app.cookies.group.description', $group['snippet_description']);
            static::assertCount(1, $group['entries']);

            static::assertSame('swag.analytics', $rows[1]['name']);
            $single = json_decode((string) $rows[1]['payload'], true, flags: \JSON_THROW_ON_ERROR);
            static::assertSame('swag-analytics', $single['cookie']);
            static::assertSame(30, $single['expiration']); // normalized string -> int
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testUpdateDestructiveDropsCookiesColumnIdempotently(): void
    {
        $this->ensureCookiesColumn();

        $migration = new Migration1784049595MigrateAppCookiesToAppFeature();
        $migration->updateDestructive($this->connection);
        // idempotent: a second run must not fail
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'app', 'cookies'));

        // restore for the rest of the suite
        $this->ensureCookiesColumn();
    }

    private function ensureCookiesColumn(): void
    {
        if (TableHelper::columnExists($this->connection, 'app', 'cookies')) {
            return;
        }

        $this->connection->executeStatement(
            'ALTER TABLE `app`
                ADD COLUMN `cookies` JSON NULL,
                ADD CONSTRAINT `json.app.cookies` CHECK (JSON_VALID(`cookies`))'
        );
    }
}
