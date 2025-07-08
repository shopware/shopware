<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\SystemCheck\ProductListingReadinessCheck;

/**
 * @internal
 */
#[CoversClass(ProductListingReadinessCheck::class)]
class ProductListingReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $this->createSalesChannels();
    }

    public function testCheckProductListing(): void
    {
        $this->createMainNavigationWithSalesChannelAssignment($this->ids->get('sales-channel-1'), true);
        $this->createMainNavigationWithSalesChannelAssignment($this->ids->get('sales-channel-2'), false);

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testCheckProductListingWithoutProducts(): void
    {
        $this->createMainNavigationWithoutProducts();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    private function createCheck(): ProductListingReadinessCheck
    {
        return $this->getContainer()->get(ProductListingReadinessCheck::class);
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

    private function createMainNavigationWithSalesChannelAssignment(string $salesChannelId, bool $toChild): void
    {
        $context = Context::createDefaultContext();

        $parent = $this->getCategoryData(withCmsPage: false);
        $products = $this->getProductData($salesChannelId);

        $child = $this->getCategoryData(withCmsPage: true);
        $child['parentId'] = $parent['id'];
        $child['products'] = $products;

        $this->categoryRepository->upsert([$parent, $child], $context);

        $mainId = $toChild ? $child['id'] : $parent['id'];

        $this->salesChannelRepository->update([
            [
                'id' => $salesChannelId,
                'navigationCategoryId' => $mainId,
                'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            ],
        ], $context);
    }

    private function createMainNavigationWithoutProducts(): void
    {
        $context = Context::createDefaultContext();

        $parent = $this->getCategoryData(withCmsPage: false);

        $this->categoryRepository->upsert([$parent], $context);

        $this->salesChannelRepository->update([
            [
                'id' => $this->ids->get('sales-channel-1'),
                'navigationCategoryId' => $parent['id'],
                'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            ],
        ], $context);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getProductData(string $salesChannelId): array
    {
        $products = [];
        for ($i = 0; $i < 5; ++$i) {
            $products[] = (new ProductBuilder($this->ids, 'product-' . $i))
                ->name('Test-' . $i)
                ->price(10)
                ->manufacturer('manufacturer')
                ->tax('tax')
                ->visibility($salesChannelId)
                ->build();
        }

        return $products;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCategoryData(bool $withCmsPage): array
    {
        $categoryId = Uuid::randomHex();
        $pageId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $data = [
            'id' => $categoryId,
            'name' => 'Test',
            'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
            'productStreamId' => $streamId,
            'productStream' => [
                'id' => $streamId,
                'name' => 'test',
                'filters' => [[
                    'type' => 'equals',
                    'field' => 'active',
                    'value' => '1',
                ]],
            ],
        ];

        if ($withCmsPage) {
            $data['cmsPageId'] = $pageId;
            $data['cmsPage'] = [
                'id' => $pageId,
                'type' => 'product_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'product-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $data;
    }
}
