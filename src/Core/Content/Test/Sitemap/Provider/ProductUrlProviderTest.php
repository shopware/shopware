<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Sitemap\Provider\ProductUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productSalesChannelRepository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productSalesChannelRepository = $this->getContainer()->get('sales_channel.product.repository');

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', Defaults::SALES_CHANNEL);
    }

    public function testProductUrlObjectContainsValidContent(): void
    {
        $this->createProducts();

        $urlResult = $this->getProductUrlProvider()->getUrls($this->salesChannelContext, 5);

        [$firstUrl] = $urlResult->getUrls();

        static::assertSame('hourly', $firstUrl->getChangefreq());
        static::assertSame(0.5, $firstUrl->getPriority());
        static::assertSame(ProductEntity::class, $firstUrl->getResource());
        static::assertTrue(Uuid::isValid($firstUrl->getIdentifier()));
    }

    public function testReturnedOffsetIsValid(): void
    {
        $this->createProducts();

        $productUrlProvider = $this->getProductUrlProvider();

        // first run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 3);
        static::assertSame(3, $urlResult->getNextOffset());

        // 1+n run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 2, 3);
        static::assertSame(5, $urlResult->getNextOffset());

        // last run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 100, 5); // test with high number to get last chunk
        static::assertNull($urlResult->getNextOffset());
    }

    private function getProductUrlProvider(): ProductUrlProvider
    {
        return new ProductUrlProvider(
            $this->productSalesChannelRepository,
            $this->getContainer()->get(ConfigHandler::class),
            $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class)
        );
    }

    private function createProducts(): void
    {
        $products = $this->getProductTestData($this->salesChannelContext);

        $this->getContainer()->get('product.repository')->create($products, $this->salesChannelContext->getContext());
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
}
