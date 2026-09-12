<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1788985964WidenSeoUrlRouteName;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1788985964WidenSeoUrlRouteName::class)]
class Migration1788985964WidenSeoUrlRouteNameTest extends TestCase
{
    private const FIXTURE_ROUTE_NAME = 'storefront.app.MigrationTestAppWithAVeryLongName.blog-detail';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `seo_url` WHERE `route_name` = :routeName',
            ['routeName' => self::FIXTURE_ROUTE_NAME]
        );

        (new Migration1788985964WidenSeoUrlRouteName())->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788985964, (new Migration1788985964WidenSeoUrlRouteName())->getCreationTimestamp());
    }

    public function testUpdateWidensTheRouteNameColumn(): void
    {
        $this->rollback();

        $migration = new Migration1788985964WidenSeoUrlRouteName();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, SeoUrlDefinition::ENTITY_NAME, 'route_name');

        static::assertSame('string', $column->type);
        static::assertSame(255, $column->length);
        static::assertTrue($column->isNotNull);
    }

    public function testUpdateSkipsTheAlterWhenTheColumnIsAlreadyWideEnough(): void
    {
        (new Migration1788985964WidenSeoUrlRouteName())->update($this->connection);
        (new Migration1788985964WidenSeoUrlRouteName())->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, SeoUrlDefinition::ENTITY_NAME, 'route_name');

        static::assertSame(255, $column->length);
    }

    public function testRouteNamesLongerThanTheOldLimitAreStoredUntruncated(): void
    {
        $this->rollback();
        (new Migration1788985964WidenSeoUrlRouteName())->update($this->connection);

        $languageId = $this->connection->fetchOne('SELECT `id` FROM `language` LIMIT 1');
        static::assertIsString($languageId);

        $id = Uuid::randomBytes();
        $this->connection->insert('seo_url', [
            'id' => $id,
            'language_id' => $languageId,
            'foreign_key' => Uuid::randomBytes(),
            'route_name' => self::FIXTURE_ROUTE_NAME,
            'path_info' => '/storefront/script/blog-detail?id=' . Uuid::randomHex(),
            'seo_path_info' => 'migration-test-' . Uuid::randomHex(),
            'is_canonical' => 1,
            'is_modified' => 1,
            'is_deleted' => 0,
            'created_at' => '2024-01-01 00:00:00.000',
        ]);

        static::assertSame(
            self::FIXTURE_ROUTE_NAME,
            $this->connection->fetchOne('SELECT `route_name` FROM `seo_url` WHERE `id` = :id', ['id' => $id])
        );
    }

    private function rollback(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `seo_url` MODIFY `route_name` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL'
        );
    }
}
