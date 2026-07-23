<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1784801407AddHeadlessSeoUrlTemplate;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1784801407AddHeadlessSeoUrlTemplate::class)]
class Migration1784801407AddHeadlessSeoUrlTemplateTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1784801407, (new Migration1784801407AddHeadlessSeoUrlTemplate())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->rollback();

        // idempotent
        $this->migrate();
        $this->migrate();

        static::assertTrue(TableHelper::columnExists($this->connection, 'seo_url_template', 'is_headless'));

        $storeApiDefaults = $this->connection->fetchAllKeyValue(
            'SELECT route_name, template FROM seo_url_template
             WHERE sales_channel_id IS NULL AND is_headless = 1 AND route_name LIKE :prefix',
            ['prefix' => 'store-api.%']
        );

        static::assertArrayHasKey('store-api.product.detail', $storeApiDefaults);
        static::assertArrayHasKey('store-api.category.detail', $storeApiDefaults);
        static::assertArrayHasKey('store-api.landing-page.detail', $storeApiDefaults);

        // the product default mirrors the storefront counterpart's template (matched by entity)
        $storefrontProduct = $this->connection->fetchOne(
            'SELECT template FROM seo_url_template
             WHERE entity_name = :entity AND sales_channel_id IS NULL AND is_headless = 0',
            ['entity' => 'product']
        );
        static::assertSame($storefrontProduct, $storeApiDefaults['store-api.product.detail']);
    }

    private function migrate(): void
    {
        (new Migration1784801407AddHeadlessSeoUrlTemplate())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `seo_url_template` WHERE `route_name` LIKE \'store-api.%\''
        );

        if (TableHelper::columnExists($this->connection, 'seo_url_template', 'is_headless')) {
            $this->connection->executeStatement('ALTER TABLE `seo_url_template` DROP COLUMN `is_headless`');
        }
    }
}
