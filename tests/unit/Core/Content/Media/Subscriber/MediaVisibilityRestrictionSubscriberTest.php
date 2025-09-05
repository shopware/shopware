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
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeEntityAggregationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryDefinition;

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
            BeforeEntityAggregationEvent::class => 'securePrivateMediaAggregation',
        ];

        static::assertSame($expected, MediaVisibilityRestrictionSubscriber::getSubscribedEvents());
    }

    public function testSecurePrivateFoldersSystemContextDoesNotGetModified(): void
    {
        $subscriber = new MediaVisibilityRestrictionSubscriber();

        $searchedEvent = new EntitySearchedEvent(
            new Criteria(),
            new MediaFolderDefinition(),
            Context::createCLIContext()
        );
        $subscriber->securePrivateFolders($searchedEvent);

        static::assertCount(0, $searchedEvent->getCriteria()->getFilters());

        $criteria = new Criteria();
        $countAggregation = new CountAggregation('folder-count', 'id');
        $criteria->addAggregation($countAggregation);
        $aggregatingEvent = new BeforeEntityAggregationEvent(
            $criteria,
            new MediaFolderDefinition(),
            Context::createCLIContext()
        );
        $subscriber->securePrivateMediaAggregation($aggregatingEvent);

        static::assertSame(
            $countAggregation,
            $criteria->getAggregations()[\array_key_first($criteria->getAggregations())]
        );
    }

    public function testSecurePrivateFlagIgnoresNonMediaEntities(): void
    {
        $subscriber = new MediaVisibilityRestrictionSubscriber();

        $searchedEvent = new EntitySearchedEvent(
            new Criteria(),
            new CountryDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );
        $subscriber->securePrivateFolders($searchedEvent);

        static::assertCount(0, $searchedEvent->getCriteria()->getFilters());

        $criteria = new Criteria();
        $countAggregation = new CountAggregation('folder-count', 'id');
        $criteria->addAggregation($countAggregation);
        $aggregatingEvent = new BeforeEntityAggregationEvent(
            $criteria,
            new CountryDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );
        $subscriber->securePrivateMediaAggregation($aggregatingEvent);

        static::assertSame(
            $countAggregation,
            $criteria->getAggregations()[\array_key_first($criteria->getAggregations())]
        );
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

    public function testPrivateMediaFolderAggregationIsRestricted(): void
    {
        $criteria = new Criteria();
        $criteria->addAggregation(
            new CountAggregation('folder-count', 'id')
        );

        $event = new BeforeEntityAggregationEvent(
            $criteria,
            new MediaFolderDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = new MediaVisibilityRestrictionSubscriber();
        $subscriber->securePrivateMediaAggregation($event);

        static::assertCount(1, $event->getCriteria()->getAggregations());

        $sanitizedAggregation = $event->getCriteria()->getAggregations()[\array_key_first($event->getCriteria()->getAggregations())];
        static::assertInstanceOf(FilterAggregation::class, $sanitizedAggregation);
        static::assertInstanceOf(CountAggregation::class, $sanitizedAggregation->getAggregation());
        static::assertStringStartsWith('Sanitized', $sanitizedAggregation->getName());
    }

    public function testPrivateMediaAggregationIsRestricted(): void
    {
        $criteria = new Criteria();
        $criteria->addAggregation(
            new CountAggregation('media-count', 'id')
        );

        $event = new BeforeEntityAggregationEvent(
            $criteria,
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = new MediaVisibilityRestrictionSubscriber();
        $subscriber->securePrivateMediaAggregation($event);

        static::assertCount(1, $event->getCriteria()->getAggregations());

        $sanitizedAggregation = $event->getCriteria()->getAggregations()[\array_key_first($event->getCriteria()->getAggregations())];
        static::assertInstanceOf(FilterAggregation::class, $sanitizedAggregation);
        static::assertInstanceOf(CountAggregation::class, $sanitizedAggregation->getAggregation());
        static::assertStringStartsWith('Sanitized', $sanitizedAggregation->getName());
    }

    public function testAddRestrictionToFilterAggregation(): void
    {
        $aggregation = new FilterAggregation(
            'test-filter',
            new CountAggregation('count', 'id'),
            [new EqualsFilter('private', true)]
        );

        $criteria = new Criteria();
        $criteria->addAggregation($aggregation);

        $event = new BeforeEntityAggregationEvent(
            $criteria,
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = new MediaVisibilityRestrictionSubscriber();
        $subscriber->securePrivateMediaAggregation($event);

        static::assertCount(1, $event->getCriteria()->getAggregations());

        $filterAggregation = $event->getCriteria()->getAggregation('test-filter');
        static::assertInstanceOf(FilterAggregation::class, $filterAggregation);
        static::assertCount(2, $filterAggregation->getFilter());
    }
}
