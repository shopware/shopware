<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SearchPageLoader::class)]
class SearchPageLoaderTest extends TestCase
{
    public function testItLoad(): void
    {
        $request = new Request(['search' => 'test']);
        $salesChannelContext = $this->getSalesChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($salesChannelContext, $request) {
                static::assertInstanceOf(SearchPageLoadedEvent::class, $event);
                static::assertSame($salesChannelContext, $event->getSalesChannelContext());
                static::assertSame($request, $event->getRequest());

                return $event;
            });

        $searchPageLoader = new SearchPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AbstractProductSearchRoute::class),
            $eventDispatcher,
            $this->createMock(AbstractTranslator::class),
        );

        $page = $searchPageLoader->load($request, $salesChannelContext);

        static::assertSame('test', $page->getSearchTerm());
    }

    public function testItLoadWithoutSearchTerm(): void
    {
        $request = new Request();
        $salesChannelContext = $this->getSalesChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($salesChannelContext, $request) {
                static::assertInstanceOf(SearchPageLoadedEvent::class, $event);
                static::assertSame($salesChannelContext, $event->getSalesChannelContext());
                static::assertSame($request, $event->getRequest());

                return $event;
            });

        $searchPageLoader = new SearchPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->createMock(AbstractProductSearchRoute::class),
            $eventDispatcher,
            $this->createMock(AbstractTranslator::class),
        );

        $page = $searchPageLoader->load($request, $salesChannelContext);

        static::assertSame('', $page->getSearchTerm());
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannelEntity,
        );
    }
}
