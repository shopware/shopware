<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainCollection;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Shopware\Storefront\Framework\SystemCheck\Util\StorefrontHealthCheckResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductDetailReadinessCheck::class)]
class ProductDetailReadinessCheckTest extends TestCase
{
    private SalesChannelDomainUtil&Stub $util;

    private AbstractSalesChannelDomainProvider&Stub $domainProvider;

    private AbstractSalesChannelContextFactory&Stub $contextFactory;

    private IdsCollection $ids;

    /**
     * @var list<string>
     */
    private array $contextSalesChannelIds = [];

    private int $handledRequests = 0;

    protected function setUp(): void
    {
        $this->domainProvider = static::createStub(SalesChannelDomainProvider::class);
        $this->ids = new IdsCollection();

        $this->initUtilMock();
        $this->initContextFactoryMock();
    }

    public function testName(): void
    {
        $check = $this->createCheck();
        static::assertSame('ProductDetailReadiness', $check->name());
    }

    public function testCategory(): void
    {
        $check = $this->createCheck();
        static::assertSame('FEATURE', $check->category()->name);
    }

    public function testAllowedToRunIn(): void
    {
        $check = $this->createCheck();
        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::PRE_ROLLOUT));
    }

    public function testRunSuccessfully(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);

        $check = $this->createCheck($this->productSearchResults());
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('ProductDetailReadiness', $result->name);
        static::assertSame('Product detail pages are OK for provided sales channels.', $result->message);
        static::assertSame('OK', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(200, $result->extra[0]['responseCode']);
        static::assertSame(200, $result->extra[1]['responseCode']);
    }

    public function testRunSkipped(): void
    {
        $this->domainProvider->method('fetchSalesChannelDomains')->willReturn(new SalesChannelDomainCollection([]));
        $this->initCreateEmptyResult();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('ProductDetailReadiness', $result->name);
        static::assertSame('No sales channels with product detail pages found.', $result->message);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertCount(0, $result->extra);
    }

    public function testRunFailed(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_INTERNAL_SERVER_ERROR);

        $check = $this->createCheck($this->productSearchResults());
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame('ProductDetailReadiness', $result->name);
        static::assertSame('Some or all product detail pages are unhealthy.', $result->message);
        static::assertSame('FAILURE', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(500, $result->extra[0]['responseCode']);
        static::assertSame(500, $result->extra[1]['responseCode']);
    }

    public function testSalesChannelsWithoutVisibleProductAreSkipped(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        // no sales channel has a product the storefront would render, e.g. because every product is
        // restricted to a rule that does not match for an anonymous visitor
        $check = $this->createCheck([[], [], []]);
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertSame(0, $this->handledRequests);
    }

    public function testProductIsResolvedWithStorefrontVisibilityCriteria(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $criteria = null;
        $this->createCheck([
            function (Criteria $actual) use (&$criteria) {
                $criteria = $actual;

                return [];
            },
            [],
            [],
        ])->run();

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(1, $criteria->getLimit());
        static::assertEquals([new EqualsFilter('available', true)], $criteria->getFilters());
        static::assertEquals([new FieldSorting('id')], $criteria->getSorting());

        // the ProductAvailableFilter must be added by SalesChannelProductDefinition::processCriteria(),
        // otherwise the check no longer shares the visibility handling of the storefront
        foreach ($criteria->getFilters() as $filter) {
            static::assertNotInstanceOf(ProductAvailableFilter::class, $filter);
        }
    }

    public function testSalesChannelContextIsCreatedPerSalesChannel(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $this->createCheck([[], [], []])->run();

        static::assertSame([
            $this->ids->get('sales-channel-1'),
            $this->ids->get('sales-channel-2'),
            $this->ids->get('sales-channel-3'),
        ], $this->contextSalesChannelIds);
    }

    /**
     * @param array<callable(Criteria, SalesChannelContext): list<string>|list<string>> $searchResults
     */
    private function createCheck(array $searchResults = []): ProductDetailReadinessCheck
    {
        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository($searchResults);

        return new ProductDetailReadinessCheck(
            $this->util,
            $this->domainProvider,
            $productRepository,
            $this->contextFactory
        );
    }

    /**
     * @return list<list<string>>
     */
    private function productSearchResults(): array
    {
        // the third sales channel has no product the storefront would render
        return [
            [$this->ids->get('product-1')],
            [$this->ids->get('product-2')],
            [],
        ];
    }

    private function initUtilMock(): void
    {
        $this->util = static::createStub(SalesChannelDomainUtil::class);
        $this->util->method('runAsSalesChannelRequest')
            ->willReturnCallback(static function (callable $callback): mixed {
                return $callback();
            });

        $this->util->method('runWhileTrustingAllHosts')
            ->willReturnCallback(static function (callable $callback): mixed {
                return $callback();
            });

        $this->util->method('generateDomainUrl')->willReturnCallback(static function ($domain, $routeName) {
            return $domain . $routeName;
        });
    }

    private function initHandleRequest(int $responseCode): void
    {
        $this->util->method('handleRequest')->willReturnCallback(
            function () use ($responseCode): StorefrontHealthCheckResult {
                ++$this->handledRequests;

                return StorefrontHealthCheckResult::create(
                    'http://localhost:8000/product/123',
                    $responseCode,
                    1.23
                );
            }
        );
    }

    private function initContextFactoryMock(): void
    {
        $this->contextFactory = static::createStub(SalesChannelContextFactory::class);
        $this->contextFactory->method('create')->willReturnCallback(
            function (string $token, string $salesChannelId) {
                $this->contextSalesChannelIds[] = $salesChannelId;

                return Generator::generateSalesChannelContext();
            }
        );
    }

    private function initDomainMocks(): void
    {
        $collection = new SalesChannelDomainCollection([
            SalesChannelDomain::create($this->ids->get('sales-channel-1'), 'http://localhost:8000/de'),
            SalesChannelDomain::create($this->ids->get('sales-channel-2'), 'http://localhost:8000/en'),
            SalesChannelDomain::create($this->ids->get('sales-channel-3'), 'http://localhost:8000/invalid'),
        ]);

        $this->domainProvider->method('fetchSalesChannelDomains')->willReturn($collection);
    }

    private function initCreateEmptyResult(): void
    {
        $this->util->method('createEmptyResult')
            ->willReturn(new Result(
                'ProductDetailReadiness',
                Status::SKIPPED,
                'No sales channels with product detail pages found.',
                true,
                []
            ));
    }
}
