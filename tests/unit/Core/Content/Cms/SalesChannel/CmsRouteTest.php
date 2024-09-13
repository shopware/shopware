<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CmsRoute::class)]
class CmsRouteTest extends TestCase
{
    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
    }

    public function testGetDecorated(): void
    {
        $pageLoader = $this->createMock(SalesChannelCmsPageLoaderInterface::class);
        $route = new CmsRoute($pageLoader);

        static::expectException(DecorationPatternException::class);
        $route->getDecorated();
    }

    public function testLoadHandlesSlotsAsArray(): void
    {
        $request = new Request([
            'slots' => [
                $this->ids->get('slot-1'),
                $this->ids->get('slot-2'),
                $this->ids->get('slot-3'),
            ],
        ]);

        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = $this->getExpectedCriteria($request->get('slots'));
        $context = Generator::createSalesChannelContext();

        $pageLoader = $this->createMock(SalesChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->method('load')
            ->with($request, $criteria, $context)
            ->willReturn($searchResult);

        $route = new CmsRoute($pageLoader);
        $response = $route->load($this->ids->get('cms-page'), $request, $context);

        $actualCmsPage = $response->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadHandlesSlotsAsString(): void
    {
        $expectedSlots = [
            $this->ids->get('slot-1'),
            $this->ids->get('slot-2'),
            $this->ids->get('slot-3'),
        ];

        $request = new Request([
            'slots' => "{$this->ids->get('slot-1')}|{$this->ids->get('slot-2')}|{$this->ids->get('slot-3')}",
        ]);

        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = $this->getExpectedCriteria($expectedSlots);
        $context = Generator::createSalesChannelContext();

        $pageLoader = $this->createMock(SalesChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->method('load')
            ->with($request, $criteria, $context)
            ->willReturn($searchResult);

        $route = new CmsRoute($pageLoader);
        $response = $route->load($this->ids->get('cms-page'), $request, $context);

        $actualCmsPage = $response->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadCmsPageWithoutProvidedSlots(): void
    {
        $request = new Request([]);
        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = new Criteria([$this->ids->get('cms-page')]);
        $context = Generator::createSalesChannelContext();

        $pageLoader = $this->createMock(SalesChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->method('load')
            ->with($request, $criteria, $context)
            ->willReturn($searchResult);

        $route = new CmsRoute($pageLoader);
        $response = $route->load($this->ids->get('cms-page'), $request, $context);

        $actualCmsPage = $response->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadThrowsExceptionIfNoPageFound(): void
    {
        $request = new Request([]);

        // empty search result
        $searchResult = $this->getSearchResult();

        $criteria = new Criteria([$this->ids->get('cms-page')]);
        $context = Generator::createSalesChannelContext();

        $pageLoader = $this->createMock(SalesChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->method('load')
            ->with($request, $criteria, $context)
            ->willReturn($searchResult);

        $route = new CmsRoute($pageLoader);

        static::expectException(PageNotFoundException::class);
        $route->load($this->ids->get('cms-page'), $request, $context);
    }

    /**
     * @param array<string> $slots
     */
    private function getExpectedCriteria(array $slots): Criteria
    {
        $criteria = new Criteria([$this->ids->get('cms-page')]);
        $criteria
            ->getAssociation('sections.blocks')
            ->addFilter(new EqualsAnyFilter('slots.id', $slots));

        return $criteria;
    }

    /**
     * @return EntitySearchResult<CmsPageCollection>&MockObject
     */
    private function getSearchResult(?CmsPageEntity $cmsPage = null): EntitySearchResult&MockObject
    {
        $searchResult = $this->createMock(EntitySearchResult::class);

        $searchResult
            ->method('has')
            ->with($this->ids->get('cms-page'))
            ->willReturn((bool) $cmsPage);

        $searchResult
            ->method('get')
            ->with($this->ids->get('cms-page'))
            ->willReturn($cmsPage);

        return $searchResult;
    }
}
