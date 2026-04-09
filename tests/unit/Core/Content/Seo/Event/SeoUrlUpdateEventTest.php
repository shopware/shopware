<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlUpdateEvent::class)]
class SeoUrlUpdateEventTest extends TestCase
{
    public function testGetters(): void
    {
        $seoUrls = [
            ['seoPathInfo' => '/test', 'pathInfo' => '/detail/1'],
        ];
        $context = Context::createDefaultContext();

        $event = new SeoUrlUpdateEvent($seoUrls, $context);

        static::assertSame($seoUrls, $event->getSeoUrls());
        static::assertSame($context, $event->getContext());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new SeoUrlUpdateEvent([]);

        $this->expectException(SeoException::class);
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new SeoUrlUpdateEvent([]);

        static::assertNull(@$event->getNullableContext());
    }
}
