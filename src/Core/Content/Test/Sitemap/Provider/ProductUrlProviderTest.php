<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
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
        $urlResult = $this->getProductUrlProvider()->getUrls($this->salesChannelContext, 5);

        [$firstUrl] = $urlResult->getUrls();

        static::assertSame('daily', $firstUrl->getChangefreq());
        static::assertSame(0.5, $firstUrl->getPriority());
        static::assertSame(ProductEntity::class, $firstUrl->getResource());
        static::assertTrue(Uuid::isValid($firstUrl->getIdentifier()));
    }

    public function testReturnedOffsetIsValid(): void
    {
        $products = [
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 3',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 4',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 100,
                'name' => 'test product 5',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
        ];

        $this->getContainer()->get('product.repository')->create($products, $this->salesChannelContext->getContext());

        $productUrlProvider = $this->getProductUrlProvider();

        // first run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 3);
        static::assertSame(3, $urlResult->getNextOffset());

        // 1+n run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 3, 3);
        static::assertSame(6, $urlResult->getNextOffset());

        // last run
        $urlResult = $productUrlProvider->getUrls($this->salesChannelContext, 6, 3);
        static::assertNull($urlResult->getNextOffset());
    }

    private function getProductUrlProvider(): ProductUrlProvider
    {
        return new ProductUrlProvider($this->productSalesChannelRepository, $this->getContainer()->get('router.default'), $this->getContainer()->get(ConfigHandler::class));
    }
}
