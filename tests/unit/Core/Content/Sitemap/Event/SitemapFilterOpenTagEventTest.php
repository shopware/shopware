<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Event\SitemapFilterOpenTagEvent;
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
}
