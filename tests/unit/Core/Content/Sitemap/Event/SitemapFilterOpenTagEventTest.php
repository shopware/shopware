<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Event\SitemapFilterOpenTagEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SitemapFilterOpenTagEvent::class)]
class SitemapFilterOpenTagEventTest extends TestCase
{
    public function testFullOpenTagRendersEveryRegisteredNamespace(): void
    {
        $event = new SitemapFilterOpenTagEvent(static::createStub(SalesChannelContext::class));
        $event->setUrlsetNamespaces([
            'xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
            'xmlns:image' => 'http://www.google.com/schemas/sitemap-image/1.1',
        ]);

        static::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">',
            $event->getFullOpenTag(),
        );
    }

    public function testSetOpenTagReplacesTheTemplate(): void
    {
        $event = new SitemapFilterOpenTagEvent(static::createStub(SalesChannelContext::class));

        static::assertStringStartsWith('<?xml version="1.0"', $event->getOpenTag());

        $event->setOpenTag('<urlset %urlsetNamespaces%>');
        $event->addUrlsetNamespace('xmlns:custom', 'https://example.com/schema');

        static::assertSame(
            ['xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9', 'xmlns:custom' => 'https://example.com/schema'],
            $event->getUrlsetNamespaces(),
        );
        static::assertSame('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:custom="https://example.com/schema">', $event->getFullOpenTag());
    }

    public function testExposesTheSalesChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $event = new SitemapFilterOpenTagEvent($salesChannelContext);

        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
