<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\SystemCheck\ProductListingReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainCollection;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Shopware\Storefront\Framework\SystemCheck\Util\StorefrontHealthCheckResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductListingReadinessCheck::class)]
class ProductListingReadinessCheckTest extends TestCase
{
    private Connection&Stub $connection;

    private SalesChannelDomainUtil&Stub $util;

    private AbstractSalesChannelDomainProvider&Stub $domainProvider;

    private AbstractSalesChannelContextFactory&Stub $contextFactory;

    private IdsCollection $ids;

    /**
     * @var list<string>
     */
    private array $requestedNavigationIds = [];

    private int $handledRequests = 0;

    /**
     * @var list<array<string, mixed>>
     */
    private array $contextOptions = [];

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->domainProvider = static::createStub(AbstractSalesChannelDomainProvider::class);
        $this->ids = new IdsCollection();

        $this->initUtilMock();
        $this->initContextFactoryMock();
    }

    public function testName(): void
    {
        $check = $this->createCheck();
        static::assertSame('ProductListingReadiness', $check->name());
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
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);

        $check = $this->createCheck($this->visibleCategoryResults());
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('ProductListingReadiness', $result->name);
        static::assertSame('Product listing pages are OK for provided sales channels.', $result->message);
        static::assertSame('OK', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(200, $result->extra[0]['responseCode']);
        static::assertSame(200, $result->extra[1]['responseCode']);
    }

    public function testRunSkipped(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->initDomainMocks();
        $this->initCreateEmptyResult();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('ProductListingReadiness', $result->name);
        static::assertSame('No sales channels with product listing pages found.', $result->message);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertCount(0, $result->extra);
    }

    public function testRunFailed(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_INTERNAL_SERVER_ERROR);

        $check = $this->createCheck($this->visibleCategoryResults());
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame('ProductListingReadiness', $result->name);
        static::assertSame('Some or all product listing pages are unhealthy.', $result->message);
        static::assertSame('FAILURE', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(500, $result->extra[0]['responseCode']);
        static::assertSame(500, $result->extra[1]['responseCode']);
    }

    public function testSalesChannelsWithoutVisibleCategoryAreSkipped(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        // no candidate category can be rendered, e.g. because all of them are restricted to a rule
        // that does not match for an anonymous visitor
        $check = $this->createCheck([[], []]);
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertSame(0, $this->handledRequests);
    }

    public function testRestrictedCategoryFallsBackToTheNextCandidate(): void
    {
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initDomainMocks();

        $this->connection->method('fetchAllAssociative')->willReturn([
            ['sales_channel_id' => $this->ids->get('sales-channel-1'), 'category_id' => $this->ids->get('restricted-category'), 'is_navigation_category' => 0],
            ['sales_channel_id' => $this->ids->get('sales-channel-1'), 'category_id' => $this->ids->get('visible-category'), 'is_navigation_category' => 0],
        ]);

        $check = $this->createCheck([[$this->ids->get('visible-category')]]);
        $result = $check->run();

        static::assertSame('OK', $result->status->name);
        static::assertSame([$this->ids->get('visible-category')], $this->requestedNavigationIds);
    }

    public function testCategoriesAreResolvedByTheCandidatesOfTheSalesChannel(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $criteria = null;
        $this->createCheck([
            function (Criteria $actual) use (&$criteria) {
                $criteria = $actual;

                return [];
            },
            [],
        ])->run();

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame([$this->ids->get('category-1')], $criteria->getIds());
    }

    /**
     * The URL that is probed belongs to one domain, and language, currency and domain all feed the
     * criteria processing that decides which categories are visible. Looking the category up with the
     * sales channel defaults instead would evaluate a restriction against a different context than the
     * request that follows.
     */
    #[TestDox('The lookup context describes the domain whose URL is probed, not the sales channel defaults')]
    public function testTheLookupContextIsBuiltForTheProbedDomain(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $this->createCheck([[], []])->run();

        static::assertNotSame([], $this->contextOptions);
        static::assertSame([
            SalesChannelContextService::DOMAIN_ID => $this->ids->get('domain-sales-channel-1'),
            SalesChannelContextService::LANGUAGE_ID => $this->ids->get('language-sales-channel-1'),
            SalesChannelContextService::CURRENCY_ID => $this->ids->get('currency-sales-channel-1'),
        ], $this->contextOptions[0]);
    }

    /**
     * @param array<callable(Criteria, SalesChannelContext): list<string>|list<string>> $searchResults
     */
    private function createCheck(array $searchResults = []): ProductListingReadinessCheck
    {
        /** @var StaticSalesChannelRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = new StaticSalesChannelRepository($searchResults);

        return new ProductListingReadinessCheck(
            $this->util,
            $this->connection,
            $this->domainProvider,
            $categoryRepository,
            $this->contextFactory
        );
    }

    /**
     * @return list<list<string>>
     */
    private function visibleCategoryResults(): array
    {
        return [
            [$this->ids->get('category-1')],
            [$this->ids->get('category-2')],
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

        $this->util->method('generateDomainUrl')->willReturnCallback(
            function (string $domain, string $routeName, array $parameters = []): string {
                $this->requestedNavigationIds[] = (string) ($parameters['navigationId'] ?? '');

                return $domain . $routeName;
            }
        );
    }

    private function initHandleRequest(int $responseCode): void
    {
        $this->util->method('handleRequest')->willReturnCallback(
            function () use ($responseCode): StorefrontHealthCheckResult {
                ++$this->handledRequests;

                return StorefrontHealthCheckResult::create(
                    'http://localhost:8000/products',
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
            function (string $token, string $salesChannelId, array $options = []): SalesChannelContext {
                $this->contextOptions[] = $options;

                return Generator::generateSalesChannelContext();
            }
        );
    }

    private function initDataMocks(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['sales_channel_id' => $this->ids->get('sales-channel-1'), 'category_id' => $this->ids->get('category-1'), 'is_navigation_category' => 0],
            ['sales_channel_id' => $this->ids->get('sales-channel-2'), 'category_id' => $this->ids->get('category-2'), 'is_navigation_category' => 0],
        ]);

        $this->initDomainMocks();
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
                'ProductListingReadiness',
                Status::SKIPPED,
                'No sales channels with product listing pages found.',
                true,
                []
            ));
    }
}
