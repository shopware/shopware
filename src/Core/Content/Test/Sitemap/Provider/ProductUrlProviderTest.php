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
            $loc = $this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $product['id']]);
            $urlGenerate = $this->seoUrlPlaceholderHandler->replace($loc, $host, $this->salesChannelContext);
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
        $products = $this->getProductTestData($this->salesChannelContext);

        $this->getContainer()->get('product.repository')->create($products, $this->salesChannelContext->getContext());

        return $products;
    }

    private function getProductTestData(SalesChannelContext $salesChannelContext): array
    {
        $taxId = $salesChannelContext->getTaxRules()->first()->getId();

        $products = [
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 3',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 4',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 5',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $taxId],
                'manufacturer' => ['name' => 'test'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
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
}
