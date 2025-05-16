<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;

/**
 * @internal
 */
#[CoversClass(ProductDetailReadinessCheck::class)]
class ProductDetailReadinessCheckTest extends TestCase
{
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
        $this->createProducts();
    }

    public function testAllChecksAreHealthy(): void
    {
        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
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
                    'url' => 'https://test.to',
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
                    'url' => 'https://foo.to',
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
            $products[] = [
                'id' => Uuid::randomHex(),
                'active' => true,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test ' . $index,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => Uuid::randomHex(), 'name' => 'test'],
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['salesChannelId' => $id, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $this->productRepository->create($products, Context::createDefaultContext());

        return $products;
    }
}
