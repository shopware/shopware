<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1610523204AddInheritanceForProductCmsPage;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Migration1610523204AddInheritanceForProductCmsPageTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10078', $this);

        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->repository = $this->getContainer()->get('product.repository');
    }

    public function testVariantCmsPageShouldInheritedFromContainerProduct(): void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);

        $database = $connection->fetchColumn('select database();');

        $cmsPageColumnExist = $connection->fetchColumn('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_NAME = \'product\' AND COLUMN_NAME = \'cmsPage\' AND TABLE_SCHEMA = "' . $database . '";');
        if ($cmsPageColumnExist) {
            $connection->executeUpdate('ALTER TABLE product DROP COLUMN cmsPage');
        }

        $migration = new Migration1610523204AddInheritanceForProductCmsPage();
        $migration->update($connection);

        $expectedCmsPageId = Uuid::randomHex();

        $context = $this->createSalesChannelContext();

        $colorOptionId = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();

        $product = $this->createData([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'configuratorSettings' => [
                [
                    'option' => [
                        'id' => $colorOptionId,
                        'name' => 'Red',
                        'group' => [
                            'id' => $colorGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
            ],
            'visibilities' => [[
                'salesChannelId' => $context->getSalesChannelId(),
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]],
            'tax' => ['id' => $context->getTaxRules()->first()->getId(), 'name' => 'test', 'taxRate' => 15],
            'cmsPage' => [
                'id' => $expectedCmsPageId,
                'type' => 'product_detail',
                'sections' => [],
            ],
        ]);

        $childProduct = $this->createData([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'tax' => ['id' => $context->getTaxRules()->first()->getId(), 'name' => 'test', 'taxRate' => 15],
            'parentId' => $product['id'],
            'options' => [
                ['id' => $colorOptionId],
            ],
        ]);

        $criteria = new Criteria([$childProduct['id']]);
        $criteria->addAssociation('cmsPage');

        $result = $this->repository->search($criteria, $context->getContext());

        static::assertNotEmpty($childProduct = $result->get($childProduct['id']));
        static::assertEquals($product['id'], $childProduct->getParentId());
        static::assertInstanceOf(CmsPageEntity::class, $cmsPage = $childProduct->getCmsPage());
        static::assertEquals($expectedCmsPageId, $cmsPage->getId());
        static::assertEquals('product_detail', $cmsPage->getType());
    }

    private function createData(array $config = []): array
    {
        $product = [
            'id' => $this->ids->create('product'),
            'manufacturer' => ['id' => $this->ids->create('manufacturer-'), 'name' => 'test-'],
            'productNumber' => $this->ids->get('product'),
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'active' => true,
        ];

        $product = array_replace_recursive($product, $config);

        $this->repository->create([$product], Context::createDefaultContext());

        return $product;
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $salesChannelContextFactory->create($token, Defaults::SALES_CHANNEL);
    }
}
