<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use Doctrine\DBAL\Connection;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\Country\CountryDefinition;

/**
 * @internal
 */
#[CoversClass(MediaVisibilityRestrictionSubscriber::class)]
class MediaVisibilityRestrictionSubscriberTest extends TestCase
{
    private const PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID = '018f1f0e0dc0719badc0ffee00000000';

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
        $subscriber = $this->createSubscriber();

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

        static::assertSame($countAggregation, array_first($criteria->getAggregations()));
    }

    public function testSecurePrivateFlagIgnoresNonMediaEntities(): void
    {
        $subscriber = $this->createSubscriber();

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

        static::assertSame($countAggregation, array_first($criteria->getAggregations()));
    }

    public function testSecurePrivateFoldersMediaFolder(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaFolderDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = $this->createSubscriber();
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

        $subscriber = $this->createSubscriber();
        $subscriber->securePrivateFolders($event);

        static::assertCount(1, $event->getCriteria()->getFilters());

        $mediaRestriction = $event->getCriteria()->getFilters()[0];
        static::assertInstanceOf(MultiFilter::class, $mediaRestriction);
        static::assertSame(MultiFilter::CONNECTION_OR, $mediaRestriction->getOperator());
        static::assertCount(2, $mediaRestriction->getQueries());

        $publicMediaRestriction = $mediaRestriction->getQueries()[0];
        self::assertEqualsFilter($publicMediaRestriction, 'private', false);

        $privateProductDownloadMediaRestriction = $mediaRestriction->getQueries()[1];
        static::assertInstanceOf(MultiFilter::class, $privateProductDownloadMediaRestriction);
        static::assertSame(MultiFilter::CONNECTION_AND, $privateProductDownloadMediaRestriction->getOperator());
        static::assertCount(2, $privateProductDownloadMediaRestriction->getQueries());
        self::assertEqualsFilter($privateProductDownloadMediaRestriction->getQueries()[0], 'private', true);
        self::assertEqualsFilter(
            $privateProductDownloadMediaRestriction->getQueries()[1],
            'mediaFolderId',
            self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID
        );
    }

    public function testResetClearsMemoizedProductDownloadMediaFolderId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID,
                '018f1f0e0dc0719badc0ffee00000001'
            );

        $subscriber = new MediaVisibilityRestrictionSubscriber($connection);

        $firstEvent = new EntitySearchedEvent(
            new Criteria(),
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );
        $subscriber->securePrivateFolders($firstEvent);

        $subscriber->reset();

        $secondEvent = new EntitySearchedEvent(
            new Criteria(),
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );
        $subscriber->securePrivateFolders($secondEvent);

        self::assertPrivateProductDownloadMediaFolderId(
            $firstEvent->getCriteria()->getFilters()[0],
            self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID
        );
        self::assertPrivateProductDownloadMediaFolderId(
            $secondEvent->getCriteria()->getFilters()[0],
            '018f1f0e0dc0719badc0ffee00000001'
        );
    }

    public function testSecurePrivateFoldersDifferentDefinitionDoesNotGetModified(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new ProductDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = $this->createSubscriber();
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

        $subscriber = $this->createSubscriber();
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

        $subscriber = $this->createSubscriber();
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

        $subscriber = $this->createSubscriber();
        $subscriber->securePrivateMediaAggregation($event);

        static::assertCount(1, $event->getCriteria()->getAggregations());

        $filterAggregation = $event->getCriteria()->getAggregation('test-filter');
        static::assertInstanceOf(FilterAggregation::class, $filterAggregation);
        static::assertCount(2, $filterAggregation->getFilter());
    }

    private function createSubscriber(string $productDownloadMediaFolderId = self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID): MediaVisibilityRestrictionSubscriber
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn($productDownloadMediaFolderId);

        return new MediaVisibilityRestrictionSubscriber($connection);
    }

    private static function assertPrivateProductDownloadMediaFolderId(mixed $filter, string $mediaFolderId): void
    {
        static::assertInstanceOf(MultiFilter::class, $filter);
        static::assertCount(2, $filter->getQueries());

        $privateProductDownloadMediaRestriction = $filter->getQueries()[1];
        static::assertInstanceOf(MultiFilter::class, $privateProductDownloadMediaRestriction);
        self::assertEqualsFilter($privateProductDownloadMediaRestriction->getQueries()[1], 'mediaFolderId', $mediaFolderId);
    }

    private static function assertEqualsFilter(mixed $filter, string $field, string|bool|null $value): void
    {
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame($field, $filter->getField());
        static::assertSame($value, $filter->getValue());
    }
}
