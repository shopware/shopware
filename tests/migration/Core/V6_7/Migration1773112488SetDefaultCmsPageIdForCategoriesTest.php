<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1773112488SetDefaultCmsPageIdForCategories;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1773112488SetDefaultCmsPageIdForCategories::class)]
class Migration1773112488SetDefaultCmsPageIdForCategoriesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1773112488SetDefaultCmsPageIdForCategories();
        static::assertSame(1773112488, $migration->getCreationTimestamp());
    }

    public function testMigrationDoesNothingIfNoDefaultCmsPageIdIsConfigured(): void
    {
        $originalConfig = $this->getOriginalSystemConfig();

        // delete the current default cms page id
        $this->connection->delete('system_config', [
            'configuration_key' => 'core.cms.default_category_cms_page',
            'sales_channel_id' => null,
        ]);

        $categoryId = Uuid::randomBytes();
        $versionId = Uuid::fromHexToBytes('0fa91ce3e96a4bc2be4bd9ce752c3425');

        $this->connection->insert('category', [
            'id' => $categoryId,
            'version_id' => $versionId,
            'type' => 'page',
            'cms_page_id' => null,
            'product_assignment_type' => 'product',
            'active' => 1,
            'visible' => 1,
            'display_nested_products' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->migrate();

        $result = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`cms_page_id`)) FROM `category` WHERE `id` = :id',
            ['id' => $categoryId]
        );

        static::assertNull($result);

        if ($originalConfig) {
            $this->connection->insert('system_config', $originalConfig);
        }
    }

    public function testMigrationDoesNothingIfReturnedCmsPageIdIsEmpty(): void
    {
        $originalConfig = $this->getOriginalSystemConfig();

        $this->connection->update('system_config', [
            'configuration_value' => json_encode(['_value' => '']),
        ], [
            'configuration_key' => 'core.cms.default_category_cms_page',
            'sales_channel_id' => null,
        ]);

        $categoryId = Uuid::randomBytes();
        $versionId = Uuid::fromHexToBytes('0fa91ce3e96a4bc2be4bd9ce752c3425');

        $this->connection->insert('category', [
            'id' => $categoryId,
            'version_id' => $versionId,
            'type' => 'page',
            'cms_page_id' => null,
            'product_assignment_type' => 'product',
            'active' => 1,
            'visible' => 1,
            'display_nested_products' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->migrate();

        $result = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`cms_page_id`)) FROM `category` WHERE `id` = :id',
            [
                'id' => $categoryId,
            ]
        );

        static::assertNull($result);

        if ($originalConfig) {
            $this->connection->update('system_config', [
                'configuration_value' => json_encode(['_value' => $originalConfig['configuration_value']]),
            ], [
                'configuration_key' => 'core.cms.default_category_cms_page',
                'sales_channel_id' => null,
            ]);
        }
    }

    public function testMigrationDoesNotOverwriteExistingCmsPageId(): void
    {
        $existingCmsPageId = $this->getAnyCmsPageId();
        if ($existingCmsPageId === null) {
            static::markTestSkipped('No product list CMS page available');
        }

        $categoryId = Uuid::randomBytes();
        $versionId = Uuid::fromHexToBytes('0fa91ce3e96a4bc2be4bd9ce752c3425');

        $this->connection->insert('category', [
            'id' => $categoryId,
            'version_id' => $versionId,
            'type' => 'page',
            'cms_page_id' => Uuid::fromHexToBytes($existingCmsPageId),
            'cms_page_version_id' => $versionId,
            'product_assignment_type' => 'product',
            'active' => 1,
            'visible' => 1,
            'display_nested_products' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->migrate();

        $result = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`cms_page_id`)) FROM `category` WHERE `id` = :id',
            ['id' => $categoryId]
        );

        static::assertSame($existingCmsPageId, $result);
    }

    public function testMigrationCanRunMultipleTimes(): void
    {
        $this->migrate();
        $this->migrate();

        $nullCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `category` WHERE `cms_page_id` IS NULL'
        );

        $defaultCmsPageId = $this->getDefaultCmsPageId();
        if ($defaultCmsPageId !== null) {
            static::assertSame(0, $nullCount);
        }
    }

    private function migrate(): void
    {
        (new Migration1773112488SetDefaultCmsPageIdForCategories())->update($this->connection);
    }

    private function getOriginalSystemConfig(): ?array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => 'core.cms.default_category_cms_page']
        );
    }

    private function getDefaultCmsPageId(): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => 'core.cms.default_category_cms_page']
        );

        if ($result === false) {
            return null;
        }

        $decoded = json_decode((string) $result, true);

        return $decoded['_value'] ?? null;
    }

    private function getAnyCmsPageId(): ?string
    {
        $result = $this->connection->fetchOne('SELECT LOWER(HEX(`id`)) FROM `cms_page` LIMIT 1');

        return $result ?: null;
    }
}
