<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
#[CoversClass(SeoUrlUpdateEvent::class)]
class SeoUrlUpdateEventTest extends TestCase
{
    public function testGetContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new SeoUrlUpdateEvent([]);

        static::assertNull($event->getContext());
    }
}
