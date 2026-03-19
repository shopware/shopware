<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private string $originalDefaultCmsPageId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->originalDefaultCmsPageId = $this->getDefaultCmsPageId();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1773112488SetDefaultCmsPageIdForCategories();
        static::assertSame(1773112488, $migration->getCreationTimestamp());
    }

    public function testMigrationDoesNothingIfNoDefaultCmsPageIdIsConfigured(): void
    {
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

        $this->restoreOriginalSystemConfig();
    }

    #[DataProvider('dataProviderForTestMigrationDoesNothingIfReturnedCmsPageIdIsInvalid')]
    public function testMigrationDoesNothingIfReturnedCmsPageIdIsInvalid(mixed $cmsPageId): void
    {
        $this->connection->update('system_config', [
            'configuration_value' => json_encode(['_value' => $cmsPageId]),
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

        $this->restoreOriginalSystemConfig();
    }

    public static function dataProviderForTestMigrationDoesNothingIfReturnedCmsPageIdIsInvalid(): \Generator
    {
        yield 'not a string' => [123];
        yield 'empty string' => [''];
    }

    public function testMigrationDoesNotOverwriteExistingCmsPageId(): void
    {
        $existingCmsPageId = $this->getAnyCmsPageId();
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

        static::assertSame(0, $nullCount);
    }

    private function migrate(): void
    {
        (new Migration1773112488SetDefaultCmsPageIdForCategories())->update($this->connection);
    }

    private function getDefaultCmsPageId(): string
    {
        $result = $this->connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => 'core.cms.default_category_cms_page']
        );

        if ($result === false) {
            $anyCmsPageId = $this->getAnyCmsPageId();
            $this->originalDefaultCmsPageId = $anyCmsPageId;
            $this->restoreOriginalSystemConfig();

            return $anyCmsPageId;
        }

        $decoded = json_decode((string) $result, true);

        return $decoded['_value'] ?? $this->getAnyCmsPageId();
    }

    private function getAnyCmsPageId(): string
    {
        $result = $this->connection->fetchOne('SELECT LOWER(HEX(`id`)) FROM `cms_page` LIMIT 1');

        if ($result === false) {
            $cmsPageId = Uuid::randomBytes();

            $this->connection->insert('cms_page', [
                'id' => $cmsPageId,
                'type' => 'page',
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return Uuid::fromBytesToHex($cmsPageId);
        }

        return $result;
    }

    private function restoreOriginalSystemConfig(): void
    {
        $this->connection->update('system_config', [
            'configuration_value' => json_encode(['_value' => $this->originalDefaultCmsPageId]),
        ], [
            'configuration_key' => 'core.cms.default_category_cms_page',
            'sales_channel_id' => null,
        ]);
    }
}
