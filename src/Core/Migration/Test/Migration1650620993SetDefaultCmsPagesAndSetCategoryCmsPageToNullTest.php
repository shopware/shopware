<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;

/**
 * @internal
 */
class Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNullTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private EntityRepositoryInterface $systemConfigRepository;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->systemConfigRepository = $this->getContainer()->get('system_config.repository');
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
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('configurationKey', $systemConfigKey));

            $expectedCmsPageId = $this->getDefaultCmsPageIdFromType($cmsPageType);

            /** @var SystemConfigEntity $systemConfigEntry */
            $systemConfigEntry = $this->systemConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

            static::assertEquals($systemConfigKey, $systemConfigEntry->getConfigurationKey());
            static::assertEquals($expectedCmsPageId, $systemConfigEntry->getConfigurationValue());
        }
    }

    public function testItSetsCategoryCmsPageToNullIfNecessary(): void
    {
        $migration = new Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull();

        $categoryId = $this->insertDemoData('product_list');

        // assert demo data were inserted and cmsPageId is not null
        static::assertNotNull($this->getCategoryIdWithDefaultCmsPage($categoryId));

        // it can be executed multiple times
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        // assert migration works as expected and cmsPageId is null
        static::assertNull($this->getCategoryIdWithDefaultCmsPage($categoryId));
    }

    private function insertDemoData(string $cmsPageType): string
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

    private function getDefaultCmsPageIdFromType(string $cmsPageType): string
    {
        $cmsPageId = $this->connection->fetchOne('
            SELECT id
            FROM  cms_page
            WHERE type = :cmsPageType AND locked = 1
            ORDER BY created_at ASC;
       ', ['cmsPageType' => $cmsPageType]);

        return Uuid::fromBytesToHex($cmsPageId);
    }

    private function getCategoryIdWithDefaultCmsPage(string $categoryId): ?string
    {
        return $this->connection->fetchOne('
            SELECT cms_page_id
            FROM  category
            WHERE id = :categoryId
            ORDER BY created_at ASC;
       ', ['categoryId' => Uuid::fromHexToBytes($categoryId)]);
    }
}
