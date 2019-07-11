<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Language\LanguageEntity;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlCollection;

class EntityExtensionReadTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $this->connection->rollBack();

        $this->connection->executeQuery('
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `product_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.product.id` FOREIGN KEY (`product_id`)
                    REFERENCES `product` (`id`)
            )
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeQuery('DROP TABLE `extended_product`');
        $this->connection->beginTransaction();

        $this->removeExtension(ProductExtension::class);

        parent::tearDown();
    }

    public function testICanReadNestedAssociationsFromToOneExtensions(): void
    {
        $productId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'shopware AG',
                    'link' => 'https://shopware.com',
                ],
                'toOne' => [
                    'name' => 'test',
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociationPath('toOne.toOne');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->get($productId);
        static::assertTrue($product->hasExtension('toOne'));

        /** @var ArrayEntity $extension */
        $extension = $product->getExtension('toOne');
        static::assertInstanceOf(ProductEntity::class, $extension->get('toOne'));
    }

    public function testICanReadNestedAssociationsFromToManyExtensions(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createSalesChannel($salesChannelId);

        /** @var EntityRepositoryInterface $salesChannelRepo */
        $salesChannelRepo = $this->getContainer()->get('sales_channel.repository');
        $context = Context::createDefaultContext();

        $salesChannelRepo->update([
            [
                'id' => $salesChannelId,
                'seoUrls' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'foreignKey' => $salesChannelId,
                        'routeName' => 'test',
                        'pathInfo' => 'test',
                        'seoPathInfo' => 'test',
                    ],
                ],
            ],
        ], $context);

        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociationPath('seoUrls.language');

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $salesChannelRepo->search($criteria, $context)->get($salesChannelId);
        static::assertTrue($salesChannel->hasExtension('seoUrls'));

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $salesChannel->getExtension('seoUrls');
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);
        static::assertCount(1, $seoUrls);

        $seoUrl = $seoUrls->first();
        static::assertInstanceOf(LanguageEntity::class, $seoUrl->getLanguage());
    }

    private function createSalesChannel($id): void
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
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $this->getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());
    }
}
