<?php

namespace Shopware\Storefront\Test\Pagelet;


use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;

class ListingPageletLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ListingPageletLoader
     */
    private $loader;

    /**
     * @var string
     */
    private $salesChannelId1;

    /**
     * @var CheckoutContextFactory
     */
    private $contextFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = $this->getContainer()->get(ListingPageletLoader::class);
        $this->salesChannelId1 = $this->createSalesChannel();

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->contextFactory = $this->getContainer()->get(CheckoutContextFactory::class);
    }

    public function testProductVisibility(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();

        $products = [
            $this->createProduct($id1, []),
            $this->createProduct($id2, [$this->salesChannelId1 => ProductVisibilityDefinition::VISIBILITY_SEARCH]),
            $this->createProduct($id3, [$this->salesChannelId1 => ProductVisibilityDefinition::VISIBILITY_ALL]),
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->create($products, $context);

        $checkoutContext = $this->contextFactory->create(Uuid::uuid4()->getHex(), $this->salesChannelId1);

        $pagelet = $this->loader->load(new InternalRequest(), $checkoutContext);

        static::assertCount(2, $pagelet);
        static::assertTrue($pagelet->has($id2));
        static::assertTrue($pagelet->has($id3));

        $request = new InternalRequest();
        $request->addParam(ListingPageletLoader::PRODUCT_VISIBILITY, ProductVisibilityDefinition::VISIBILITY_ALL);

        $pagelet = $this->loader->load($request, $checkoutContext);
        static::assertCount(1, $pagelet);
        static::assertTrue($pagelet->has($id3));

        $request = new InternalRequest();
        $request->addParam(ListingPageletLoader::PRODUCT_VISIBILITY, ProductVisibilityDefinition::VISIBILITY_LINK);

        $pagelet = $this->loader->load($request, $checkoutContext);
        static::assertCount(2, $pagelet);
        static::assertTrue($pagelet->has($id3));
        static::assertTrue($pagelet->has($id2));
    }

    private function createProduct(string $id, array $visibilities): array
    {
        $mapped = [];
        foreach ($visibilities as $salesChannel => $visibility) {
            $mapped[] = ['salesChannelId' => $salesChannel, 'visibility' => $visibility];
        }

        return [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => $mapped,
        ];
    }

    private function createSalesChannel(): string
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => Defaults::COUNTRY,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => Defaults::SHIPPING_METHOD]],
            'paymentMethods' => [['id' => Defaults::PAYMENT_METHOD_INVOICE]],
            'countries' => [['id' => Defaults::COUNTRY]],
            'name' => 'first sales-channel',
        ];

        $this->getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }

}
