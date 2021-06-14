<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Provider;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Sitemap\Provider\ProductUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Symfony\Component\Routing\RouterInterface;

class ProductUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productSalesChannelRepository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $seoUrlSalesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlPlaceholderHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productSalesChannelRepository = $this->getContainer()->get('sales_channel.product.repository');
        $this->seoUrlSalesChannelRepository = $this->getContainer()->get('sales_channel.seo_url.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->seoUrlPlaceholderHandler = $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $this->salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test-product-sitemap');
    }

    public function testProductUrlObjectContainsValidContent(): void
    {
        $products = $this->createProducts();

        $urlResult = $this->getProductUrlProvider()->getUrls($this->salesChannelContext, 5);

        $urls = $urlResult->getUrls();

        $firstUrl = $urls[0];

        static::assertSame('hourly', $firstUrl->getChangefreq());
        static::assertSame(0.5, $firstUrl->getPriority());
        static::assertSame(ProductEntity::class, $firstUrl->getResource());
        static::assertTrue(Uuid::isValid($firstUrl->getIdentifier()));

        $host = $this->getHost($this->salesChannelContext);

        foreach ($products as $product) {
            $urlGenerate = $this->getComparisonUrl($product['id']);
            $check = false;
            foreach ($urls as $url) {
                if ($urlGenerate === $host . '/' . $url->getLoc()) {
                    $check = true;

                    break;
                }
            }
            static::assertTrue($check);
        }
    }

    public function testReturnedOffsetIsValid(): void
    {
        $this->createProducts();

        $productUrlProvider = $this->getProductUrlProvider();

        // first run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 3);
        static::assertIsNumeric($urlResult->getNextOffset());

        // 1+n run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 2, $urlResult->getNextOffset());
        static::assertIsNumeric($urlResult->getNextOffset());

        // last run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 100, $urlResult->getNextOffset()); // test with high number to get last chunk
        static::assertNull($urlResult->getNextOffset());
    }

    public function testOnlyVariantUrlsGenerated(): void
    {
        $parentId = Uuid::randomHex();
        $products = [
            array_merge([
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test parent 1',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'parentId' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test variant 1',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'parentId' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test variant 2',
            ], $this->getBasicProductData()),
        ];
        $this->productRepository->create($products, $this->salesChannelContext->getContext());

        $urlResult = $this->getProductUrlProvider()->getUrls($this->salesChannelContext, 3);
        $host = $this->getHost($this->salesChannelContext);
        $locations = array_map(function ($url) use ($host) {
            return $host . '/' . $url->getLoc();
        }, $urlResult->getUrls());

        foreach ($products as $product) {
            $urlGenerate = $this->getComparisonUrl($product['id']);
            if ($product['id'] === $parentId) {
                static::assertNotContains($urlGenerate, $locations);
            } else {
                static::assertContains($urlGenerate, $locations);
            }
        }
    }

    public function testOnlyCanonicalVariantUrlGenerated(): void
    {
        $parentId = Uuid::randomHex();
        $canonicalProductId = Uuid::randomHex();
        $products = [
            array_merge([
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test parent 2',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'parentId' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test variant 3',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => $canonicalProductId,
                'parentId' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test variant canonical',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'parentId' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test variant 4',
            ], $this->getBasicProductData()),
        ];
        $this->productRepository->create($products, $this->salesChannelContext->getContext());
        $this->productRepository->update(
            [['id' => $parentId, 'canonicalProductId' => $canonicalProductId]],
            $this->salesChannelContext->getContext()
        );

        $urlResult = $this->getProductUrlProvider()->getUrls($this->salesChannelContext, 4);
        $urls = $urlResult->getUrls();

        static::assertCount(1, $urls);

        $host = $this->getHost($this->salesChannelContext);
        $urlGenerate = $this->getComparisonUrl($canonicalProductId);
        static::assertEquals($urlGenerate, $host . '/' . $urls[0]->getLoc());
    }

    private function getProductUrlProvider(): ProductUrlProvider
    {
        return new ProductUrlProvider(
            $this->getContainer()->get(ConfigHandler::class),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(ProductDefinition::class),
            $this->getContainer()->get(IteratorFactory::class),
            $this->getContainer()->get(RouterInterface::class),
        );
    }

    private function createProducts(): array
    {
        $products = $this->getProductTestData();

        $this->getContainer()->get('product.repository')->create($products, $this->salesChannelContext->getContext());

        return $products;
    }

    private function getProductTestData(): array
    {
        $products = [
            array_merge([
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'name' => 'test product 1',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'name' => 'test product 2',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'name' => 'test product 3',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'name' => 'test product 4',
            ], $this->getBasicProductData()),
            array_merge([
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'name' => 'test product 5',
            ], $this->getBasicProductData()),
        ];

        return $products;
    }

    private function getHost(SalesChannelContext $context): string
    {
        $domains = $context->getSalesChannel()->getDomains();
        $languageId = $context->getSalesChannel()->getLanguageId();

        if ($domains instanceof SalesChannelDomainCollection) {
            foreach ($domains as $domain) {
                if ($domain->getLanguageId() === $languageId) {
                    return $domain->getUrl();
                }
            }
        }

        throw new InvalidDomainException('Empty domain');
    }

    private function getComparisonUrl(string $productId): string
    {
        $loc = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $productId]);

        return $this->seoUrlPlaceholderHandler->replace($loc, $this->getHost($this->salesChannelContext), $this->salesChannelContext);
    }

    private function getBasicProductData(): array
    {
        $taxId = $this->salesChannelContext->getTaxRules()->first()->getId();

        return [
            'stock' => 100,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['id' => $taxId],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                ['salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }
}
