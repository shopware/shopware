<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull
 */
class Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNullTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testItSetsTheDefaultsInTheSystemConfig(): void
    {
        $migration = new Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull();
        $migration->update($this->connection);

        $cmsPageTypes = [
            'product_list' => CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY,
            'product_detail' => ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT,
        ];

        foreach ($cmsPageTypes as $cmsPageType => $systemConfigKey) {
            $result = $this->connection->fetchAssociative(
                'SELECT configuration_key, configuration_value
                FROM `system_config`
                WHERE configuration_key = :systemConfigKey',
                ['systemConfigKey' => $systemConfigKey]
            );
            static::assertNotFalse($result, 'A SQL select error occurred');

            static::assertEquals($systemConfigKey, $result['configuration_key']);
            static::assertEquals(
                $this->getDefaultCmsPageIdFromType($cmsPageType),
                (\json_decode((string) $result['configuration_value'], true, 512, \JSON_THROW_ON_ERROR))['_value']
            );
        }
    }

    public function testItSetsTheDefaultInTheSystemConfigWhenPageIsNotLocked(): void
    {
        // delete default product list page
        $this->connection->executeStatement(
            'DELETE FROM cms_page WHERE type = :type AND locked = :locked;',
            [
                'type' => 'product_list',
                'locked' => 1,
            ]
        );

        // insert new page which is not locked
        $this->insertProductListCmsPageDemoData();

        $migration = new Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull();
        $migration->update($this->connection);

        $result = $this->connection->fetchAssociative(
            'SELECT configuration_key, configuration_value
                FROM `system_config`
                WHERE configuration_key = :systemConfigKey',
            ['systemConfigKey' => CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY]
        );
        static::assertNotFalse($result, 'A SQL select error occurred');

        static::assertEquals(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY, $result['configuration_key']);
        static::assertNotNull((\json_decode((string) $result['configuration_value'], true, 512, \JSON_THROW_ON_ERROR))['_value']);
    }

    public function testItSetsCategoryCmsPageToNullIfNecessary(): void
    {
        $migration = new Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull();

        $categoryId = $this->insertCategoryDemoData('product_list');

        // assert demo data were inserted and cmsPageId is not null
        static::assertNotNull($this->getCategoryIdWithDefaultCmsPage($categoryId));

        // it can be executed multiple times
        $migration->update($this->connection);
        $migration->update($this->connection);

        // assert migration works as expected and cmsPageId is null
        static::assertNull($this->getCategoryIdWithDefaultCmsPage($categoryId));
    }

    private function insertCategoryDemoData(string $cmsPageType): string
    {
        $categoryId = Uuid::randomBytes();

        $data = [
            'id' => $categoryId,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => 'category',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'cms_page_id' => Uuid::fromHexToBytes($this->getDefaultCmsPageIdFromType($cmsPageType)),
            'cms_page_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ];

        $this->connection->insert('category', $data);

        return Uuid::fromBytesToHex($categoryId);
    }

    private function insertProductListCmsPageDemoData(): string
    {
        $cmsPageId = Uuid::randomBytes();
        $data = [
            'id' => $cmsPageId,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => 'product_list',
            'locked' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->connection->insert('cms_page', $data);

        return Uuid::fromBytesToHex($cmsPageId);
    }

    private function getDefaultCmsPageIdFromType(string $cmsPageType): string
    {
        $cmsPageId = $this->connection->fetchOne(
            'SELECT id
            FROM  cms_page
            WHERE type = :cmsPageType
            ORDER BY locked DESC, created_at ASC;',
            ['cmsPageType' => $cmsPageType]
        );

        return Uuid::fromBytesToHex($cmsPageId);
    }

    private function getCategoryIdWithDefaultCmsPage(string $categoryId): ?string
    {
        return $this->connection->fetchOne(
            'SELECT cms_page_id
            FROM  category
            WHERE id = :categoryId
            ORDER BY created_at ASC;',
            ['categoryId' => Uuid::fromHexToBytes($categoryId)]
        );
    }
}
