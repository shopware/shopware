<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SalesChannel\Event\SalesChannelProcessCriteriaEvent;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;

/**
 * @internal
 */
#[Package('discovery')]
class ProductDetailReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use EventDispatcherBehaviour;
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

    public function testCheckIsHealthyWhenAllProductsAreRestrictedByAnExtension(): void
    {
        $productIds = $this->getProductIds($this->createProducts());

        // simulates an extension that restricts product visibility by rules, e.g. Dynamic Access:
        // for an anonymous visitor the rule does not match, so the products are filtered out
        $this->restrictProducts($productIds);

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    public function testCheckPicksAProductThatIsNotRestrictedByAnExtension(): void
    {
        $productIds = $this->getProductIds($this->createProducts());
        $additionalProductIds = $this->getProductIds($this->createProducts('additional-product-'));

        // restrict the product that would be selected first, the check has to fall back to the other one
        $restrictedIds = [];
        foreach ($productIds as $index => $productId) {
            $ids = [$productId, $additionalProductIds[$index]];
            sort($ids);
            $restrictedIds[] = $ids[0];
        }

        $this->restrictProducts($restrictedIds);

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
        static::assertCount(2, $result->extra);
    }

    /**
     * @param list<string> $productIds
     */
    private function restrictProducts(array $productIds): void
    {
        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            'sales_channel.product.process.criteria',
            static function (SalesChannelProcessCriteriaEvent $event) use ($productIds): void {
                $event->getCriteria()->addFilter(
                    new NotFilter(NotFilter::CONNECTION_AND, [new EqualsAnyFilter('product.id', $productIds)])
                );
            }
        );
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<string>
     */
    private function getProductIds(array $products): array
    {
        return array_map(static fn (array $product) => (string) $product['id'], $products);
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
    private function createProducts(string $keyPrefix = 'product-'): array
    {
        $salesChannelIds = [
            $this->ids->get('sales-channel-1'),
            $this->ids->get('sales-channel-2'),
        ];

        $products = [];
        foreach ($salesChannelIds as $index => $id) {
            $products[] = (new ProductBuilder($this->ids, $keyPrefix . $index))
                ->name('Test-' . $keyPrefix . $index)
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
