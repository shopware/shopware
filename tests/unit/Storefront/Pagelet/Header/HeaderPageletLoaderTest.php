<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Pagelet\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRouteResponse;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Shopware\Core\System\Language\SalesChannel\LanguageRouteResponse;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(HeaderPageletLoader::class)]
class HeaderPageletLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $salesChannelContext = Generator::generateSalesChannelContext();

        $currencyRoute = $this->createMock(AbstractCurrencyRoute::class);
        $currencyRoute->method('load')->willReturn(new CurrencyRouteResponse(new CurrencyCollection([
            (new CurrencyEntity())->assign(['id' => $salesChannelContext->getCurrencyId()]),
        ])));

        $languageRoute = $this->createMock(AbstractLanguageRoute::class);
        $languageRoute->method('load')->willReturn(new LanguageRouteResponse(new EntitySearchResult(
            LanguageDefinition::ENTITY_NAME,
            1,
            new LanguageCollection([
                (new LanguageEntity())->assign(['id' => $salesChannelContext->getLanguageId()]),
            ]),
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        )));

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $category1 = (new CategoryEntity())->assign(['id' => $categoryId1]);
        $category2 = (new CategoryEntity())->assign(['id' => $categoryId2]);
        $navigationCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
        $navigationLoader->method('load')->willReturnMap(
            [
                [
                    $navigationCategoryId,
                    $salesChannelContext,
                    $navigationCategoryId,
                    $salesChannelContext->getSalesChannel()->getNavigationCategoryDepth(),
                    new Tree($category2, [new TreeItem($category1, []), new TreeItem($category2, [])]),
                ],
            ]
        );

        $headerPageletLoader = new HeaderPageletLoader($eventDispatcher, $currencyRoute, $languageRoute, $navigationLoader);
        $header = $headerPageletLoader->load(new Request(), $salesChannelContext);

        static::assertSame($salesChannelContext->getLanguageId(), $header->getActiveLanguage()->getId());
        static::assertSame($salesChannelContext->getCurrencyId(), $header->getActiveCurrency()->getId());

        $navigation = $header->getNavigation();
        static::assertNotNull($navigation);
        $tree = $navigation->getTree();
        static::assertCount(2, $tree);
        static::assertSame($categoryId1, $tree[0]->getCategory()->getId());
        static::assertSame($categoryId2, $tree[1]->getCategory()->getId());
    }
}
