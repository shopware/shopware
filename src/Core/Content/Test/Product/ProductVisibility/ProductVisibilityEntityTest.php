<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\ProductVisibility;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
class ProductVisibilityEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    protected $productRepository;

    private string $salesChannelId1;

    private string $salesChannelId2;

    /**
     * @var EntityRepository
     */
    private $visibilityRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->visibilityRepository = $this->getContainer()->get('product_visibility.repository');

        $this->salesChannelId1 = Uuid::randomHex();
        $this->salesChannelId2 = Uuid::randomHex();

        $this->createSalesChannel($this->salesChannelId1);
        $this->createSalesChannel($this->salesChannelId2);
    }

    public function testVisibilityCRUD(): void
    {
        $id = Uuid::randomHex();

        $product = $this->createProduct(
            $id,
            [
                $this->salesChannelId1 => ProductVisibilityDefinition::VISIBILITY_SEARCH,
                $this->salesChannelId2 => ProductVisibilityDefinition::VISIBILITY_LINK,
            ]
        );

        $context = Context::createDefaultContext();

        $container = $this->productRepository->create([$product], $context);

        $event = $container->getEventByEntityName(ProductVisibilityDefinition::ENTITY_NAME);

        //visibility created?
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(2, $event->getWriteResults());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('visibilities');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->first();

        //check visibilities can be loaded as association
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductVisibilityCollection::class, $product->getVisibilities());
        static::assertCount(2, $product->getVisibilities());

        //check read for visibilities
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_visibility.productId', $id));

        $visibilities = $this->visibilityRepository->search($criteria, $context);
        static::assertCount(2, $visibilities);

        //test filter visibilities over product
        $criteria = new Criteria([$id]);

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new RangeFilter('product.visibilities.visibility', [
                        RangeFilter::GTE => ProductVisibilityDefinition::VISIBILITY_LINK,
                    ]),
                    new EqualsFilter('product.visibilities.salesChannelId', $this->salesChannelId1),
                ]
            )
        );

        $product = $this->productRepository->search($criteria, $context)->first();

        //visibilities filtered and loaded?
        static::assertInstanceOf(ProductEntity::class, $product);

        $ids = $visibilities->map(
            fn (ProductVisibilityEntity $visibility) => ['id' => $visibility->getId()]
        );

        $container = $this->visibilityRepository->delete(array_values($ids), $context);

        $event = $container->getEventByEntityName(ProductVisibilityDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(2, $event->getWriteResults());
    }

    private function createProduct(string $id, array $visibilities): array
    {
        $mapped = [];
        foreach ($visibilities as $salesChannel => $visibility) {
            $mapped[] = ['salesChannelId' => $salesChannel, 'visibility' => $visibility];
        }

        return [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => $mapped,
        ];
    }

    private function createSalesChannel(string $id): void
    {
        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $this->getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());
    }
}
