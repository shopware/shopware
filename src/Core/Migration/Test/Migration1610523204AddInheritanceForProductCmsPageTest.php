<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1610523204AddInheritanceForProductCmsPage;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 * NEXT-21735 - Not deterministic due to SalesChannelContextFactory
 *
 * @group not-deterministic
 */
#[Package('core')]
class Migration1610523204AddInheritanceForProductCmsPageTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestDataCollection $ids;

    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->repository = $this->getContainer()->get('product.repository');
    }

    public function testVariantCmsPageShouldInheritedFromContainerProduct(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $database = $connection->fetchOne('select database();');

        $cmsPageColumnExist = $connection->fetchOne('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_NAME = \'product\' AND COLUMN_NAME = \'cmsPage\' AND TABLE_SCHEMA = "' . $database . '";');

        $connection->rollBack();

        if ($cmsPageColumnExist) {
            $connection->executeStatement('ALTER TABLE product DROP COLUMN cmsPage');
        }

        $migration = new Migration1610523204AddInheritanceForProductCmsPage();
        $migration->update($connection);

        $connection->beginTransaction();

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

        $childProduct = $result->get($childProduct['id']);
        static::assertInstanceOf(ProductEntity::class, $childProduct);
        static::assertEquals($product['id'], $childProduct->getParentId());
        static::assertInstanceOf(CmsPageEntity::class, $cmsPage = $childProduct->getCmsPage());
        static::assertEquals($expectedCmsPageId, $cmsPage->getId());
        static::assertEquals('product_detail', $cmsPage->getType());
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
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

        return $salesChannelContextFactory->create($token, TestDefaults::SALES_CHANNEL);
    }
}
