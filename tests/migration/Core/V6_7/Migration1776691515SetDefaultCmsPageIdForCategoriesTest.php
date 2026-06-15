<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1776691515SetDefaultCmsPageIdForCategories;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1776691515SetDefaultCmsPageIdForCategories::class)]
class Migration1776691515SetDefaultCmsPageIdForCategoriesTest extends TestCase
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
        $migration = new Migration1776691515SetDefaultCmsPageIdForCategories();
        static::assertSame(1776691515, $migration->getCreationTimestamp());
    }

    public function testMigrationUsesConfiguredDefaultCmsPageId(): void
    {
        $configuredCmsPageId = $this->getAnyCmsPageId();
        $this->connection->update('system_config', [
            'configuration_value' => json_encode(['_value' => $configuredCmsPageId], \JSON_THROW_ON_ERROR),
        ], [
            'configuration_key' => CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY,
            'sales_channel_id' => null,
        ]);

        $categoryId = $this->insertCategoryWithoutCmsPageId();

        $this->migrate();

        static::assertSame($configuredCmsPageId, $this->getConfiguredDefaultCmsPageId());
        static::assertSame($configuredCmsPageId, $this->getCategoryCmsPageId($categoryId));
    }

    public function testMigrationSkipsIfNoDefaultCmsPageIdIsConfigured(): void
    {
        $this->connection->delete('system_config', [
            'configuration_key' => CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY,
            'sales_channel_id' => null,
        ]);

        $categoryId = $this->insertCategoryWithoutCmsPageId();

        $this->migrate();

        static::assertNull($this->getConfiguredDefaultCmsPageId());
        static::assertNull($this->getCategoryCmsPageId($categoryId));
    }

    #[DataProvider('dataProviderForInvalidConfiguredDefaultCmsPageId')]
    public function testMigrationSkipsIfConfiguredDefaultCmsPageIdIsInvalid(mixed $cmsPageId): void
    {
        $this->setConfiguredDefaultCmsPageConfigurationValue(
            json_encode(['_value' => $cmsPageId], \JSON_THROW_ON_ERROR)
        );

        $categoryId = $this->insertCategoryWithoutCmsPageId();

        $this->migrate();

        static::assertSame($cmsPageId, $this->getConfiguredDefaultCmsPageRawValue());
        static::assertNull($this->getCategoryCmsPageId($categoryId));
    }

    public static function dataProviderForInvalidConfiguredDefaultCmsPageId(): \Generator
    {
        yield 'not a string' => [123];
        yield 'empty string' => [''];
        yield 'invalid uuid string' => ['invalid-id'];
    }

    public function testMigrationSkipsIfConfiguredDefaultCmsPageDoesNotExist(): void
    {
        $configuredCmsPageId = Uuid::randomHex();
        $this->setConfiguredDefaultCmsPageConfigurationValue(
            json_encode(['_value' => $configuredCmsPageId], \JSON_THROW_ON_ERROR)
        );

        $categoryId = $this->insertCategoryWithoutCmsPageId();

        $this->migrate();

        static::assertSame($configuredCmsPageId, $this->getConfiguredDefaultCmsPageRawValue());
        static::assertNull($this->getCategoryCmsPageId($categoryId));
    }

    public function testMigrationDoesNotOverwriteExistingCmsPageId(): void
    {
        $existingCmsPageId = $this->getAnyCmsPageId();

        $categoryId = Uuid::randomHex();
        $this->connection->insert('category', [
            'id' => Uuid::fromHexToBytes($categoryId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => CategoryDefinition::TYPE_PAGE,
            'cms_page_id' => Uuid::fromHexToBytes($existingCmsPageId),
            'cms_page_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'product_assignment_type' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
            'active' => 1,
            'visible' => 1,
            'display_nested_products' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->migrate();

        static::assertSame($existingCmsPageId, $this->getCategoryCmsPageId($categoryId));
    }

    public function testMigrationCanBeRerunSafelyWithoutValidConfiguredDefaultCmsPageId(): void
    {
        $configuredCmsPageId = Uuid::randomHex();
        $this->setConfiguredDefaultCmsPageConfigurationValue(
            json_encode(['_value' => $configuredCmsPageId], \JSON_THROW_ON_ERROR)
        );
        $categoryId = $this->insertCategoryWithoutCmsPageId();

        $this->migrate();
        $this->migrate();

        static::assertSame($configuredCmsPageId, $this->getConfiguredDefaultCmsPageRawValue());
        static::assertNull($this->getCategoryCmsPageId($categoryId));
    }

    private function migrate(): void
    {
        (new Migration1776691515SetDefaultCmsPageIdForCategories())->update($this->connection);
    }

    private function insertCategoryWithoutCmsPageId(): string
    {
        $categoryId = Uuid::randomHex();

        $this->connection->insert('category', [
            'id' => Uuid::fromHexToBytes($categoryId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => CategoryDefinition::TYPE_PAGE,
            'cms_page_id' => null,
            'product_assignment_type' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
            'active' => 1,
            'visible' => 1,
            'display_nested_products' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $categoryId;
    }

    private function getCategoryCmsPageId(string $categoryId): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`cms_page_id`)) FROM `category` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($categoryId)],
            ['id' => ParameterType::BINARY]
        );

        return \is_string($result) ? $result : null;
    }

    private function getConfiguredDefaultCmsPageId(): ?string
    {
        $cmsPageId = $this->getConfiguredDefaultCmsPageRawValue();

        return \is_string($cmsPageId) ? $cmsPageId : null;
    }

    private function getConfiguredDefaultCmsPageRawValue(): mixed
    {
        $result = $this->getConfiguredDefaultCmsPageConfigurationValue();

        if ($result === false) {
            return null;
        }

        $decoded = json_decode($result, true);
        if (!\is_array($decoded)) {
            return null;
        }

        return $decoded['_value'] ?? null;
    }

    private function getConfiguredDefaultCmsPageConfigurationValue(): string|false
    {
        $result = $this->connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY]
        );

        return \is_string($result) ? $result : false;
    }

    private function setConfiguredDefaultCmsPageConfigurationValue(string $configurationValue): void
    {
        $this->connection->update('system_config', [
            'configuration_value' => $configurationValue,
        ], [
            'configuration_key' => CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY,
            'sales_channel_id' => null,
        ]);
    }

    private function getAnyCmsPageId(): string
    {
        $result = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `cms_page` WHERE `version_id` = :versionId LIMIT 1',
            ['versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['versionId' => ParameterType::BINARY]
        );

        \assert(\is_string($result));

        return $result;
    }
}
