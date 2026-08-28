<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class OneToManyInheritedAssociationFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private string $salesChannelId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->salesChannelId = Uuid::randomHex();
        $this->createSalesChannel($this->salesChannelId);
    }

    /**
     * An inherited one-to-many association is joined over the inheritance column, which
     * is written by the entity indexer. The indexer runs asynchronously in production,
     * so a criteria filtering that association must not depend on it having run yet.
     */
    public function testInheritedAssociationIsFilterableBeforeIndexerRan(): void
    {
        $id = Uuid::randomHex();

        $writeContext = Context::createDefaultContext();
        $writeContext->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $this->productRepository->create([$this->productPayload($id)], $writeContext);

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $this->salesChannelId));

        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();

        static::assertContains($id, $ids, 'Product with a matching visibility was not found while the inheritance column was still unset.');
    }

    /**
     * The same criteria must keep working once the indexer has populated the column.
     */
    public function testInheritedAssociationIsFilterableAfterIndexerRan(): void
    {
        $id = Uuid::randomHex();

        $this->productRepository->create([$this->productPayload($id)], Context::createDefaultContext());

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $this->salesChannelId));

        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();

        static::assertContains($id, $ids);
    }

    /**
     * A variant must still inherit the visibilities of its parent.
     */
    public function testVariantInheritsParentVisibility(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $this->productRepository->create([$this->productPayload($parentId)], $context);
        $this->productRepository->create([[
            'id' => $variantId,
            'parentId' => $parentId,
            'productNumber' => 'VARIANT-' . Uuid::randomHex(),
            'stock' => 5,
        ]], $context);

        $searchContext = Context::createDefaultContext();
        $searchContext->setConsiderInheritance(true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $this->salesChannelId));

        $ids = $this->productRepository->searchIds($criteria, $searchContext)->getIds();

        static::assertContains($variantId, $ids, 'Variant did not inherit the visibility of its parent.');
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $id): array
    {
        return [
            'id' => $id,
            'productNumber' => 'REPRO-' . Uuid::randomHex(),
            'stock' => 10,
            'active' => true,
            'name' => 'Inheritance filter product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => [[
                'salesChannelId' => $this->salesChannelId,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]],
        ];
    }

    private function createSalesChannel(string $id): void
    {
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getValidShippingMethodId();
        $navigationCategoryId = $this->getValidCategoryId();
        $countryId = $this->getValidCountryId();

        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $navigationCategoryId,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'countries' => [['id' => $countryId]],
            'name' => 'inheritance filter sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];

        static::getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());
    }
}
