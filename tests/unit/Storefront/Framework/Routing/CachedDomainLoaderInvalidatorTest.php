<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Framework\Routing\CachedDomainLoaderInvalidator;
use Shopware\Tests\Unit\Storefront\Theme\MockedCacheInvalidator;

/**
 * @internal
 */
#[CoversClass(CachedDomainLoaderInvalidator::class)]
class CachedDomainLoaderInvalidatorTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            [EntityWrittenContainerEvent::class => [['invalidate', 2000]]],
            CachedDomainLoaderInvalidator::getSubscribedEvents()
        );
    }

    public function testInvalidateIsCalledForSalesChannelWrittenEvent(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(SalesChannelDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertEquals([CachedDomainLoader::CACHE_KEY], $mockedInvalidator->getForceInvalidatedTags());
    }

    public function testInvalidateIsNotCalledForNonSalesChannelWrites(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(ProductDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertEquals([], $mockedInvalidator->getForceInvalidatedTags());
    }
}
