<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Pagelet\Menu\Offcanvas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(MenuOffcanvasPageletLoader::class)]
class MenuOffcanvasPageletLoaderTest extends TestCase
{
    #[DataProvider('loadProvider')]
    public function testLoad(int $navigationCategoryDepth): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setNavigationCategoryDepth($navigationCategoryDepth);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $category1 = (new CategoryEntity())->assign(['id' => $categoryId1]);
        $category2 = (new CategoryEntity())->assign(['id' => $categoryId2]);
        $navigationLoader->expects($this->once())
            ->method('load')
            /**
             * Regression test/assumption for PR #12061: Ensures navigation depth is dynamically
             * retrieved from sales channel configuration.
             */
            ->with(
                $categoryId2,
                $salesChannelContext,
                $categoryId2,
                $salesChannelContext->getSalesChannel()->getNavigationCategoryDepth()
            )
            ->willReturn(new Tree($category2, [new TreeItem($category1, []), new TreeItem($category2, [])]));

        $loader = new MenuOffcanvasPageletLoader($eventDispatcher, $navigationLoader);
        $menuOffcanvas = $loader->load(new Request(['navigationId' => $categoryId2]), $salesChannelContext);

        $navigation = $menuOffcanvas->getNavigation();
        static::assertNotNull($navigation);
        static::assertSame($categoryId2, $navigation->getActive()?->getId());

        $tree = $navigation->getTree();
        static::assertCount(2, $tree);
        static::assertSame($categoryId1, $tree[0]->getCategory()->getId());
        static::assertSame($categoryId2, $tree[1]->getCategory()->getId());
    }

    public static function loadProvider(): \Generator
    {
        for ($depth = 1; $depth <= 4; ++$depth) {
            yield \sprintf('navigation category depth %d', $depth) => [$depth];
        }
    }
}
