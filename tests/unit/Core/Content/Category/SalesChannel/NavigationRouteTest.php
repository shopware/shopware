<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Content\Category\Tree\CategoryTreePathResolver;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NavigationRoute::class)]
class NavigationRouteTest extends TestCase
{
    private NavigationRoute $navigationRoute;

    private Connection&MockObject $connection;

    /**
     * @var SalesChannelRepository<CategoryCollection>&MockObject
     */
    private SalesChannelRepository&MockObject $categoryRepository;

    private CacheTagCollector&MockObject $cacheTagCollector;

    private CategoryTreePathResolver&MockObject $categoryTreePathResolver;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->categoryRepository = $this->createMock(SalesChannelRepository::class);
        $this->cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $this->categoryTreePathResolver = $this->createMock(CategoryTreePathResolver::class);

        $this->navigationRoute = new NavigationRoute(
            $this->connection,
            $this->categoryRepository,
            $this->cacheTagCollector,
            $this->categoryTreePathResolver
        );

        $this->salesChannelContext = Generator::generateSalesChannelContext();
    }

    public function testLoadAddsCacheTagsCorrectly(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Generator::NAVIGATION_CATEGORY;
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'LOWER(HEX(`id`))' => $activeId,
                    'path' => '|' . $rootId . '|' . $activeId . '|',
                    'level' => 2,
                ],
                [
                    'LOWER(HEX(`id`))' => $rootId,
                    'path' => '|' . $rootId . '|',
                    'level' => 1,
                ],
            ]);

        $categories = new CategoryCollection();
        $searchResult = new EntitySearchResult(
            'category',
            0,
            $categories,
            null,
            $criteria,
            $this->salesChannelContext->getContext()
        );

        $this->categoryRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->categoryTreePathResolver
            ->expects($this->once())
            ->method('getAdditionalPathsToLoad')
            ->willReturn([]);

        $this->cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(NavigationRoute::ALL_TAG);

        $this->navigationRoute->load(
            $activeId,
            $rootId,
            $request,
            $this->salesChannelContext,
            $criteria
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLoadAddsDeprecatedCacheTagsCorrectly(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Generator::NAVIGATION_CATEGORY;
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'LOWER(HEX(`id`))' => $activeId,
                    'path' => '|' . $rootId . '|' . $activeId . '|',
                    'level' => 2,
                ],
                [
                    'LOWER(HEX(`id`))' => $rootId,
                    'path' => '|' . $rootId . '|',
                    'level' => 1,
                ],
            ]);

        $categories = new CategoryCollection();
        $searchResult = new EntitySearchResult(
            'category',
            0,
            $categories,
            null,
            $criteria,
            $this->salesChannelContext->getContext()
        );

        $this->categoryRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->categoryTreePathResolver
            ->expects($this->once())
            ->method('getAdditionalPathsToLoad')
            ->willReturn([]);

        $this->cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(
                NavigationRoute::ALL_TAG,
                NavigationRoute::buildName($this->salesChannelContext->getSalesChannelId()),
                NavigationRoute::buildName($activeId)
            );

        $this->navigationRoute->load(
            $activeId,
            $rootId,
            $request,
            $this->salesChannelContext,
            $criteria
        );
    }

    public function testLoadWithInvalidCategoryThrowsException(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage(
            \sprintf('Category "%s" not found.', $activeId)
        );

        $this->navigationRoute->load(
            $activeId,
            $rootId,
            $request,
            $this->salesChannelContext,
            $criteria
        );
    }
}
