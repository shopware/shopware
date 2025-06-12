<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;

/**
 * @internal
 */
#[CoversClass(ProductDetailReadinessCheck::class)]
class ProductDetailReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->ids = new IdsCollection();

        $this->createSalesChannels();
    }

    public function testAllChecksAreHealthy(): void
    {
        $this->createProducts();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testCheckWithoutProducts(): void
    {
        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    private function createCheck(): ProductDetailReadinessCheck
    {
        return $this->getContainer()->get(ProductDetailReadinessCheck::class);
    }

    private function createSalesChannels(): void
    {
        $this->connection->executeStatement('DELETE FROM `sales_channel_domain`');
        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel-1'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://shop.test',
                ],
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createProducts(): array
    {
        $salesChannelIds = [
            $this->ids->get('sales-channel-1'),
            $this->ids->get('sales-channel-2'),
        ];

        $products = [];
        foreach ($salesChannelIds as $index => $id) {
            $products[] = (new ProductBuilder($this->ids, 'product-' . $index))
                ->name('Test-' . $index)
                ->price(10)
                ->manufacturer('manufacturer')
                ->tax('tax')
                ->visibility($id)
                ->build();
        }

        $this->productRepository->create($products, Context::createDefaultContext());

        return $products;
    }
}
