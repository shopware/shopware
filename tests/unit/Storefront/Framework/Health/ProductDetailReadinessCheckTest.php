<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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

    /**
     * @var list<array<string, mixed>>
     */
    private array $contextOptions = [];

    private bool $hideCloseoutProducts = true;

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
        static::assertEquals([new ProductCloseoutFilter()], $criteria->getFilters());
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
     * @return iterable<string, array{bool, bool}>
     */
    public static function closeoutConfigProvider(): iterable
    {
        yield 'the setting hides closeout products out of stock' => [true, true];
        yield 'the setting keeps them renderable' => [false, false];
    }

    /**
     * `ProductDetailRoute::addCloseoutFilter()` only filters while the setting is on, so the check has
     * to do the same. Filtering unconditionally would skip a sales channel whose only products are
     * closeout and out of stock, even though their detail pages still render.
     */
    #[DataProvider('closeoutConfigProvider')]
    #[TestDox('When $_dataName, the closeout filter is applied: $1')]
    public function testTheCloseoutFilterFollowsTheSalesChannelSetting(bool $hideCloseoutProducts, bool $expectFilter): void
    {
        $this->hideCloseoutProducts = $hideCloseoutProducts;

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
        static::assertEquals($expectFilter ? [new ProductCloseoutFilter()] : [], $criteria->getFilters());
    }

    /**
     * The URL that is probed belongs to one domain, and language, currency and domain all feed the
     * criteria processing that decides which products are visible. Looking the product up with the
     * sales channel defaults instead would evaluate a restriction against a different context than
     * the request that follows.
     */
    #[TestDox('The lookup context describes the domain whose URL is probed, not the sales channel defaults')]
    public function testTheLookupContextIsBuiltForTheProbedDomain(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $this->createCheck([[], [], []])->run();

        static::assertCount(3, $this->contextOptions);
        static::assertSame([
            SalesChannelContextService::DOMAIN_ID => $this->ids->get('domain-sales-channel-1'),
            SalesChannelContextService::LANGUAGE_ID => $this->ids->get('language-sales-channel-1'),
            SalesChannelContextService::CURRENCY_ID => $this->ids->get('currency-sales-channel-1'),
        ], $this->contextOptions[0]);
    }

    /**
     * @param array<callable(Criteria, SalesChannelContext): list<string>|list<string>> $searchResults
     */
    private function createCheck(array $searchResults = []): ProductDetailReadinessCheck
    {
        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository($searchResults);

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturnCallback(
            fn (string $key): bool => $key === 'core.listing.hideCloseoutProductsWhenOutOfStock' && $this->hideCloseoutProducts
        );

        return new ProductDetailReadinessCheck(
            $this->util,
            $this->domainProvider,
            $productRepository,
            $this->contextFactory,
            new ProductCloseoutFilterFactory(),
            $systemConfigService,
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
            function (string $token, string $salesChannelId, array $options = []) {
                $this->contextSalesChannelIds[] = $salesChannelId;
                $this->contextOptions[] = $options;

                return Generator::generateSalesChannelContext();
            }
        );
    }

    private function initDomainMocks(): void
    {
        $collection = new SalesChannelDomainCollection([
            $this->domain('sales-channel-1', 'http://localhost:8000/de'),
            $this->domain('sales-channel-2', 'http://localhost:8000/en'),
            $this->domain('sales-channel-3', 'http://localhost:8000/invalid'),
        ]);

        $this->domainProvider->method('fetchSalesChannelDomains')->willReturn($collection);
    }

    private function domain(string $key, string $url): SalesChannelDomain
    {
        return SalesChannelDomain::create(
            $this->ids->get($key),
            $url,
            $this->ids->get('domain-' . $key),
            $this->ids->get('language-' . $key),
            $this->ids->get('currency-' . $key),
        );
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
