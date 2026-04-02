<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Extension\LoadPreviewExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\Product\Util\ExplicitProductListingIdMerger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(ProductListingLoader::class)]
class ProductListingLoaderTest extends TestCase
{
    /**
     * @var MockObject&SalesChannelRepository<ProductCollection>
     */
    private MockObject&SalesChannelRepository $productRepository;

    private MockObject&SystemConfigService $systemConfigService;

    private MockObject&Connection $connection;

    private EventDispatcher $eventDispatcher;

    private MockObject&AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->connection = $this->createMock(Connection::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->productCloseoutFilterFactory = $this->createMock(AbstractProductCloseoutFilterFactory::class);
        $this->salesChannelContext = Generator::generateSalesChannelContext();
    }

    public function testResolveIdsUsesExplicitProductListingIdMergerFlow(): void
    {
        $groupedCriteria = new Criteria();
        $groupedResult = $this->createIdSearchResult($groupedCriteria, [
            'red-l' => ['score' => 10.0],
            'blue-m' => ['score' => 5.0],
        ]);

        $matchingExplicitResult = $this->createIdSearchResult(new Criteria(), [
            'green-l' => ['score' => 8.0],
        ]);

        $this->systemConfigService
            ->expects($this->exactly(2))
            ->method('getBool')
            ->willReturnCallback(function (string $key): bool {
                static::assertContains($key, [
                    'core.listing.hideCloseoutProductsWhenOutOfStock',
                ]);

                return false;
            });

        $this->productRepository
            ->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria) use ($groupedResult, $matchingExplicitResult): IdSearchResult {
                static $call = 0;
                ++$call;

                if ($call === 1) {
                    static::assertCount(1, $criteria->getGroupFields());
                    static::assertTrue(\count(array_filter(
                        $criteria->getFilters(),
                        static fn ($filter): bool => $filter instanceof NotEqualsFilter && $filter->getField() === 'displayGroup'
                    )) > 0);

                    return $groupedResult;
                }

                static::assertSame(['green-l'], $criteria->getIds());
                static::assertNull($criteria->getOffset());
                static::assertNull($criteria->getLimit());

                return $matchingExplicitResult;
            });

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createDisplayGroupSearchResult([
                'red-l' => 'shirt-parent-a',
                'blue-m' => 'shirt-parent-b',
                'green-l' => 'shirt-parent-a',
            ]));

        $loader = $this->createLoader();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', ['green-l']));

        $result = $this->invoke($loader, 'resolveIds', [$criteria, $this->salesChannelContext]);

        static::assertSame(['blue-m', 'green-l'], $result->getIds());
    }

    public function testResolvePreviewsKeepsExplicitProductIdsAfterPreviewResolution(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with('core.listing.findBestVariant', $this->salesChannelContext->getSalesChannelId())
            ->willReturn(false);

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension): void {
                $extension->result = [
                    'explicit-id' => 'main-variant',
                    'other-id' => 'remapped-id',
                ];
                $extension->stopPropagation();
            }
        );

        $loader = $this->createLoader();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', 'explicit-id'));

        $mapping = $this->invoke($loader, 'resolvePreviews', [
            ['explicit-id', 'other-id'],
            $criteria,
            $this->salesChannelContext,
        ]);

        static::assertSame([
            'explicit-id' => 'explicit-id',
            'other-id' => 'remapped-id',
        ], $mapping);
    }

    public function testResolvePreviewsLoadsPreviewOnSearchRouteEvenWithOptionPostFilter(): void
    {
        $previewLoaded = false;

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with('core.listing.findBestVariant', $this->salesChannelContext->getSalesChannelId())
            ->willReturn(false);

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension) use (&$previewLoaded): void {
                $previewLoaded = true;
                $extension->result = [
                    'variant-id' => 'preview-id',
                ];
                $extension->stopPropagation();
            }
        );

        $loader = $this->createLoader();

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);
        $criteria->addPostFilter(new EqualsFilter('product.options.id', 'option-id'));

        $mapping = $this->invoke($loader, 'resolvePreviews', [
            ['variant-id'],
            $criteria,
            $this->salesChannelContext,
        ]);

        static::assertTrue($previewLoaded);
        static::assertSame(['variant-id' => 'preview-id'], $mapping);
    }

    public function testShouldNotLoadPreviewsOnSearchWhenFindBestVariantIsEnabled(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with('core.listing.findBestVariant', $this->salesChannelContext->getSalesChannelId())
            ->willReturn(true);

        $loader = $this->createLoader();

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);

        $shouldLoadPreviews = $this->invoke($loader, 'shouldLoadPreviews', [
            $criteria,
            $this->salesChannelContext,
        ]);

        static::assertFalse($shouldLoadPreviews);
    }

    private function createLoader(): ProductListingLoader
    {
        return new ProductListingLoader(
            $this->productRepository,
            $this->systemConfigService,
            $this->connection,
            $this->eventDispatcher,
            $this->productCloseoutFilterFactory,
            new ExtensionDispatcher($this->eventDispatcher),
            new ExplicitProductListingIdMerger(
                $this->productRepository,
                $this->systemConfigService,
                $this->productCloseoutFilterFactory
            )
        );
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invoke(object $instance, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($instance, $arguments);
    }

    /**
     * @param array<string, array<string, mixed>> $ids
     */
    private function createIdSearchResult(Criteria $criteria, array $ids): IdSearchResult
    {
        $data = [];
        foreach ($ids as $id => $row) {
            $data[$id] = [
                'primaryKey' => $id,
                'data' => $row,
            ];
        }

        return new IdSearchResult(\count($data), $data, $criteria, $this->salesChannelContext->getContext());
    }

    /**
     * @param array<string, string> $displayGroups
     */
    private function createDisplayGroupSearchResult(array $displayGroups): EntitySearchResult
    {
        $products = new EntityCollection();
        foreach ($displayGroups as $id => $displayGroup) {
            $products->add((new PartialEntity())->assign([
                'id' => $id,
                'displayGroup' => $displayGroup,
            ]));
        }

        return new EntitySearchResult('product', $products->count(), $products, null, new Criteria(), $this->salesChannelContext->getContext());
    }
}
