<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1788986059AppSeoUrlRoute;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1788986059AppSeoUrlRoute::class)]
class Migration1788986059AppSeoUrlRouteTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_seo_url_route`;');
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_seo_url_route'));

        $migration = new Migration1788986059AppSeoUrlRoute();
        static::assertSame(1788986059, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'app_seo_url_route'));

        foreach (['id', 'app_id', 'name', 'route_name', 'hook', 'entity_name', 'default_template', 'paths', 'label', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'app_seo_url_route', $column),
                \sprintf('Column "%s" is missing', $column)
            );
        }

        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'app_seo_url_route', 'uniq.app_seo_url_route.app_id_name', ['app_id', 'name']));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'app_seo_url_route', 'uniq.app_seo_url_route.route_name', ['route_name']));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'app_seo_url_route', 'fk.app_seo_url_route.app_id'));
    }
}
