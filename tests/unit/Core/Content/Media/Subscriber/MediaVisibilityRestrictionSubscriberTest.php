<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Subscriber\MediaVisibilityRestrictionSubscriber;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
#[CoversClass(MediaVisibilityRestrictionSubscriber::class)]
class MediaVisibilityRestrictionSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $expected = [
            EntitySearchedEvent::class => 'securePrivateFolders',
        ];

        static::assertSame($expected, MediaVisibilityRestrictionSubscriber::getSubscribedEvents());
    }

    public function testSecurePrivateFoldersSystemContextDoesNotGetModified(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaFolderDefinition(),
            Context::createCLIContext()
        );

        $subscriber = new MediaVisibilityRestrictionSubscriber();
        $subscriber->securePrivateFolders($event);

        static::assertCount(0, $event->getCriteria()->getFilters());
    }

    public function testSecurePrivateFoldersMediaFolder(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaFolderDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = new MediaVisibilityRestrictionSubscriber();
        $subscriber->securePrivateFolders($event);

        static::assertCount(1, $event->getCriteria()->getFilters());
    }

    public function testSecurePrivateFoldersMedia(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = new MediaVisibilityRestrictionSubscriber();
        $subscriber->securePrivateFolders($event);

        static::assertCount(1, $event->getCriteria()->getFilters());
    }

    public function testSecurePrivateFoldersDifferentDefinitionDoesNotGetModified(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new ProductDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = new MediaVisibilityRestrictionSubscriber();
        $subscriber->securePrivateFolders($event);

        static::assertCount(0, $event->getCriteria()->getFilters());
    }
}
