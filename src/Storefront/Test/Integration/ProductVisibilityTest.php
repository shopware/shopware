<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\Request;

class ProductVisibilityTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var string
     */
    private $salesChannelId1;

    /**
     * @var string
     */
    private $salesChannelId2;

    /**
     * @var string
     */
    private $productId1;

    /**
     * @var string
     */
    private $productId2;

    /**
     * @var string
     */
    private $productId3;

    /**
     * @var string
     */
    private $productId4;

    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    /**
     * @var SuggestPageLoader
     */
    private $suggestPageLoader;

    /**
     * @var SalesChannelContextFactory
     */
    private $contextFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductPageLoader
     */
    private $productPageLoader;

    /**
     * @var string
     */
    private $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchPageLoader = $this->getContainer()->get(SearchPageLoader::class);
        $this->suggestPageLoader = $this->getContainer()->get(SuggestPageLoader::class);
        $this->productPageLoader = $this->getContainer()->get(ProductPageLoader::class);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $this->insertData();
    }

    public function testVisibilityInListing(): void
    {
        $salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $this->salesChannelId1);

        $request = new Request();
        $request->attributes->set('_route_params', ['navigationId' => $this->categoryId]);

        $data = $this->getContainer()
            ->get(ProductListingRoute::class)
            ->load($this->categoryId, $request, $salesChannelContext, new Criteria())
            ->getResult();

        static::assertSame(1, $data->getTotal());
        static::assertTrue($data->has($this->productId3));

        $salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $this->salesChannelId2);

        $data = $this->getContainer()
            ->get(ProductListingRoute::class)
            ->load($this->categoryId, $request, $salesChannelContext, new Criteria())
            ->getResult();

        static::assertSame(1, $data->getTotal());
        static::assertTrue($data->has($this->productId1));
    }

    public function testVisibilityInSearch(): void
    {
        $salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $this->salesChannelId1);

        $request = new Request(['search' => 'test']);

        $page = $this->searchPageLoader->load($request, $salesChannelContext);

        static::assertCount(2, $page->getListing());
        static::assertTrue($page->getListing()->has($this->productId2));
        static::assertTrue($page->getListing()->has($this->productId3));

        $salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $this->salesChannelId2);
        $page = $this->searchPageLoader->load($request, $salesChannelContext);

        static::assertCount(2, $page->getListing());
        static::assertTrue($page->getListing()->has($this->productId1));
        static::assertTrue($page->getListing()->has($this->productId2));
    }

    public function testVisibilityOnProductPage(): void
    {
        $cases = [
            ['salesChannelId' => $this->salesChannelId1, 'productId' => $this->productId1, 'visible' => false],
            ['salesChannelId' => $this->salesChannelId1, 'productId' => $this->productId2, 'visible' => true],
            ['salesChannelId' => $this->salesChannelId1, 'productId' => $this->productId3, 'visible' => true],
            ['salesChannelId' => $this->salesChannelId1, 'productId' => $this->productId4, 'visible' => true],
            ['salesChannelId' => $this->salesChannelId2, 'productId' => $this->productId1, 'visible' => true],
            ['salesChannelId' => $this->salesChannelId2, 'productId' => $this->productId2, 'visible' => true],
            ['salesChannelId' => $this->salesChannelId2, 'productId' => $this->productId3, 'visible' => true],
            ['salesChannelId' => $this->salesChannelId2, 'productId' => $this->productId4, 'visible' => false],
        ];

        foreach ($cases as $index => $case) {
            $salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $case['salesChannelId']);

            $request = new Request([], [], ['productId' => $case['productId']]);

            $e = null;
            $page = null;

            try {
                $page = $this->productPageLoader->load($request, $salesChannelContext);
            } catch (\Exception $e) {
            }

            if ($case['visible']) {
                static::assertNull($e, 'Exception should not be thrown.');
                static::assertNotNull($page, 'Page should not be null');
                static::assertSame($case['productId'], $page->getProduct()->getId());

                continue;
            }

            static::assertInstanceOf(ProductNotFoundException::class, $e, 'case #' . $index);
        }
    }

    public function testVisibilityInSuggest(): void
    {
        $salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $this->salesChannelId1);

        $request = new Request(['search' => 'test']);

        $page = $this->suggestPageLoader->load($request, $salesChannelContext);

        static::assertCount(2, $page->getSearchResult());
        static::assertTrue($page->getSearchResult()->has($this->productId2));
        static::assertTrue($page->getSearchResult()->has($this->productId3));

        $salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), $this->salesChannelId2);
        $page = $this->searchPageLoader->load($request, $salesChannelContext);

        static::assertCount(2, $page->getListing());
        static::assertTrue($page->getListing()->has($this->productId1));
        static::assertTrue($page->getListing()->has($this->productId2));
    }

    private function insertData(): void
    {
        $this->salesChannelId1 = $this->createSalesChannel();
        $this->salesChannelId2 = $this->createSalesChannel();

        $this->categoryId = Uuid::randomHex();

        $this->productId1 = Uuid::randomHex();
        $this->productId2 = Uuid::randomHex();
        $this->productId3 = Uuid::randomHex();
        $this->productId4 = Uuid::randomHex();

        $products = [
            $this->createProduct($this->productId1, [
                $this->salesChannelId2 => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]),
            $this->createProduct($this->productId2, [
                $this->salesChannelId1 => ProductVisibilityDefinition::VISIBILITY_SEARCH,
                $this->salesChannelId2 => ProductVisibilityDefinition::VISIBILITY_SEARCH,
            ]),
            $this->createProduct($this->productId3, [
                $this->salesChannelId1 => ProductVisibilityDefinition::VISIBILITY_ALL,
                $this->salesChannelId2 => ProductVisibilityDefinition::VISIBILITY_LINK,
            ]),
            $this->createProduct($this->productId4, [
                $this->salesChannelId1 => ProductVisibilityDefinition::VISIBILITY_LINK,
            ]),
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->create($products, $context);
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
            'categories' => [
                ['id' => $this->categoryId, 'name' => 'test'],
            ],
        ];
    }

    private function createSalesChannel(): string
    {
        $id = Uuid::randomHex();

        $snippetSetId = (string) $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT id FROM snippet_set LIMIT 1');

        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'navigation' => ['name' => 'test'],
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
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'url' => 'test.de/' . $id,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => Uuid::fromBytesToHex($snippetSetId),
                ],
            ],
        ];

        $this->getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }
}
