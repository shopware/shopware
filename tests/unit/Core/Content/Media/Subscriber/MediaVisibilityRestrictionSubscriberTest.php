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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryDefinition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaVisibilityRestrictionSubscriber::class)]
class MediaVisibilityRestrictionSubscriberTest extends TestCase
{
    private const PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID = '018f1f0e0dc0719badc0ffee00000000';

    private const PRODUCT_DOCUMENT_MEDIA_FOLDER_ID = '018f1f0e0dc0719badc0ffee00000002';

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

    public function testDalWriteEventSystemContextGetsModified(): void
    {
        $subscriber = $this->createSubscriber();
        $context = Context::createCLIContext();
        $context->addState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT);

        $searchedEvent = new EntitySearchedEvent(
            new Criteria(),
            new MediaDefinition(),
            $context
        );
        $subscriber->securePrivateFolders($searchedEvent);

        static::assertCount(1, $searchedEvent->getCriteria()->getFilters());

        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('media-count', 'id'));
        $aggregatingEvent = new BeforeEntityAggregationEvent(
            $criteria,
            new MediaDefinition(),
            $context
        );
        $subscriber->securePrivateMediaAggregation($aggregatingEvent);

        static::assertInstanceOf(FilterAggregation::class, $criteria->getAggregation('Sanitized media-count'));
    }

    public function testExplicitSystemScopeStillAllowsPrivateMediaDuringDalWriteEvent(): void
    {
        $subscriber = $this->createSubscriber();
        $context = Context::createCLIContext();

        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($subscriber): void {
            $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($subscriber): void {
                $searchedEvent = new EntitySearchedEvent(
                    new Criteria(),
                    new MediaDefinition(),
                    $context
                );
                $subscriber->securePrivateFolders($searchedEvent);

                static::assertCount(0, $searchedEvent->getCriteria()->getFilters());
            });

            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
        }, [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT]);

        static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
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

        $privateAllowedMediaRestriction = $mediaRestriction->getQueries()[1];
        static::assertInstanceOf(MultiFilter::class, $privateAllowedMediaRestriction);
        static::assertSame(MultiFilter::CONNECTION_AND, $privateAllowedMediaRestriction->getOperator());
        static::assertCount(2, $privateAllowedMediaRestriction->getQueries());
        self::assertEqualsFilter($privateAllowedMediaRestriction->getQueries()[0], 'private', true);
        self::assertEqualsAnyFilter(
            $privateAllowedMediaRestriction->getQueries()[1],
            'mediaFolderId',
            [self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID, self::PRODUCT_DOCUMENT_MEDIA_FOLDER_ID]
        );
    }

    public function testResetClearsMemoizedPrivateAllowedMediaFolderIds(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls(
                [self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID],
                ['018f1f0e0dc0719badc0ffee00000001']
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

        self::assertPrivateAllowedMediaFolderIds(
            $firstEvent->getCriteria()->getFilters()[0],
            [self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID]
        );
        self::assertPrivateAllowedMediaFolderIds(
            $secondEvent->getCriteria()->getFilters()[0],
            ['018f1f0e0dc0719badc0ffee00000001']
        );
    }

    public function testPrivateProductDownloadAndProductDocumentDefaultFoldersAreVisible(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = $this->createSubscriber();
        $subscriber->securePrivateFolders($event);

        $filters = $event->getCriteria()->getFilters();
        static::assertCount(1, $filters);

        self::assertPrivateAllowedMediaFolderIds(
            $filters[0],
            [self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID, self::PRODUCT_DOCUMENT_MEDIA_FOLDER_ID]
        );
    }

    public function testPrivateMediaIsFullyRestrictedWhenNoAllowedFoldersExist(): void
    {
        $connection = static::createStub(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        $subscriber = new MediaVisibilityRestrictionSubscriber($connection);
        $subscriber->securePrivateFolders($event);

        $filters = $event->getCriteria()->getFilters();
        static::assertCount(1, $filters);

        $mediaRestriction = $filters[0];
        static::assertInstanceOf(MultiFilter::class, $mediaRestriction);
        static::assertCount(1, $mediaRestriction->getQueries());
        self::assertEqualsFilter($mediaRestriction->getQueries()[0], 'private', false);
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

    /**
     * @param list<string>|null $privateAllowedMediaFolderIds
     */
    private function createSubscriber(?array $privateAllowedMediaFolderIds = null): MediaVisibilityRestrictionSubscriber
    {
        $connection = static::createStub(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn($privateAllowedMediaFolderIds ?? [self::PRODUCT_DOWNLOAD_MEDIA_FOLDER_ID, self::PRODUCT_DOCUMENT_MEDIA_FOLDER_ID]);

        return new MediaVisibilityRestrictionSubscriber($connection);
    }

    /**
     * @param list<string> $mediaFolderIds
     */
    private static function assertPrivateAllowedMediaFolderIds(mixed $filter, array $mediaFolderIds): void
    {
        static::assertInstanceOf(MultiFilter::class, $filter);
        static::assertCount(2, $filter->getQueries());

        $privateAllowedMediaRestriction = $filter->getQueries()[1];
        static::assertInstanceOf(MultiFilter::class, $privateAllowedMediaRestriction);
        self::assertEqualsAnyFilter($privateAllowedMediaRestriction->getQueries()[1], 'mediaFolderId', $mediaFolderIds);
    }

    private static function assertEqualsFilter(mixed $filter, string $field, string|bool|null $value): void
    {
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame($field, $filter->getField());
        static::assertSame($value, $filter->getValue());
    }

    /**
     * @param list<string> $values
     */
    private static function assertEqualsAnyFilter(mixed $filter, string $field, array $values): void
    {
        static::assertInstanceOf(EqualsAnyFilter::class, $filter);
        static::assertSame($field, $filter->getField());
        static::assertSame($values, $filter->getValue());
    }
}
