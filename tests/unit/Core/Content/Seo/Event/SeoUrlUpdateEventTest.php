<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;

/**
 * @internal
 */
#[CoversClass(SeoUrlUpdateEvent::class)]
class SeoUrlUpdateEventTest extends TestCase
{
    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->expectException(FeatureException::class);
        new SeoUrlUpdateEvent([]);
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new SeoUrlUpdateEvent([]);

        $this->expectExceptionObject(SeoException::invalidEventData('No context provided. Pass $context to the constructor of ' . SeoUrlUpdateEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new SeoUrlUpdateEvent([]);

        static::assertNull($event->getNullableContext());
    }
}
