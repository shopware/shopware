<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Cms\CategoryNavigationCmsElementResolver;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CategoryNavigationCmsElementResolver::class)]
class CategoryNavigationCmsElementResolverTest extends TestCase
{
    public function testEnrich(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannel = $salesChannelContext->getSalesChannel();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $categoryId1 = $salesChannel->getNavigationCategoryId();
        $categoryId2 = Uuid::randomHex();
        $category1 = (new CategoryEntity())->assign(['id' => $categoryId1]);
        $category2 = (new CategoryEntity())->assign(['id' => $categoryId2]);
        $navigationLoader->method('load')->willReturnMap(
            [
                [
                    $categoryId2,
                    $salesChannelContext,
                    $salesChannel->getNavigationCategoryId(),
                    $salesChannel->getNavigationCategoryDepth(),
                    new Tree($category2, [new TreeItem($category1, []), new TreeItem($category2, [])]),
                ],
            ]
        );

        $slot = new CmsSlotEntity();

        $resolverContext = new ResolverContext(
            $salesChannelContext,
            new Request(['navigationId' => $categoryId2])
        );

        (new CategoryNavigationCmsElementResolver($navigationLoader))->enrich(
            $slot,
            $resolverContext,
            new ElementDataCollection()
        );

        $navigation = $slot->getData();
        static::assertInstanceOf(Tree::class, $navigation);

        static::assertSame($categoryId2, $navigation->getActive()?->getId());

        $tree = $navigation->getTree();
        static::assertCount(2, $tree);
        static::assertSame($categoryId1, $tree[0]->getCategory()->getId());
        static::assertSame($categoryId2, $tree[1]->getCategory()->getId());
    }
}
