<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(UnusedMediaPurger::class)]
class UnusedMediaPurgerTest extends TestCase
{
    public function testGetNotUsedMediaOnlyAppliesValidAssociationsToCriteria(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                function () {
                    return [];
                },
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media1, $media2], $media);
    }

    public function testGetNotUsedMediaWithPaging(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();
        $id4 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);
        $media3 = $this->createMedia($id3);
        $media4 = $this->createMedia($id4);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);
                    self::assertEquals(0, $criteria->getOffset());
                    self::assertEquals(50, $criteria->getLimit());

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                function (Criteria $criteria, Context $context) use ($id3, $id4) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);
                    self::assertEquals(50, $criteria->getOffset());
                    self::assertEquals(50, $criteria->getLimit());

                    return [$id3, $id4];
                },
                function (Criteria $criteria, Context $context) use ($id3, $id4, $media3, $media4) {
                    static::assertSame([$id3, $id4], $criteria->getIds());

                    return new MediaCollection([$media3, $media4]);
                },
                function () {
                    return [];
                },
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media1, $media2, $media3, $media4], $media);
    }

    public function testGetNotUsedMediaOnlyFetchesSingleResultSetIfLimitAndOffsetSupplied(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();
        $id4 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);
        $media3 = $this->createMedia($id3);
        $media4 = $this->createMedia($id4);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2, $id3, $id4) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2, $id3, $id4];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $id3, $id4, $media1, $media2, $media3, $media4) {
                    static::assertSame([$id1, $id2, $id3, $id4], $criteria->getIds());

                    return new MediaCollection([$media1, $media2, $media3, $media4]);
                },
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia(4, 0)));

        static::assertEquals([$media1, $media2, $media3, $media4], $media);
    }

    public function testGetNotUsedMediaCorrectlyAppliesOneToOneAssociationToCriteria(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                (new FkField('meta_id', 'metaId', 'Meta'))->addFlags(new Required()),
                new OneToOneAssociationField('meta', 'meta_id', 'id', 'Meta', false),
            ]),
            'Meta' => $this->getMetaDefinition(),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(1, $filters);

                    self::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    self::assertEquals('media.meta.id', $filters[0]->getField());
                    self::assertNull($filters[0]->getValue());

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media1, $media2], $media);
    }

    public function testGetNotUsedMediaCorrectlyAppliesOneToManyAssociationToCriteria(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                new OneToManyAssociationField('productMedia', 'ProductMedia', 'media_id', 'id'),
            ]),
            'ProductMedia' => $this->getProductMediaDefinition(),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(1, $filters);

                    self::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    self::assertEquals('media.productMedia.mediaId', $filters[0]->getField());
                    self::assertNull($filters[0]->getValue());

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media1, $media2], $media);
    }

    public function testGetNotUsedMediaCorrectlyAppliesManyToManyAssociationToCriteria(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                new ManyToManyAssociationField(
                    'galleries',
                    'MediaGallery',
                    'MediaGalleryMapping',
                    'media_id',
                    'gallery_id',
                ),
            ]),
            'MediaGallery' => $this->getMediaGalleryDefinition(),
            'MediaGalleryMapping' => $this->getMediaGalleryMappingDefinition(),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(1, $filters);

                    self::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    self::assertEquals('media.galleries.id', $filters[0]->getField());
                    self::assertNull($filters[0]->getValue());

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media1, $media2], $media);
    }

    public function testGetNotUsedMediaIgnoresAggregateEntities(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                (new OneToManyAssociationField('thumbnails', 'Thumbnail', 'media_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            ]),
            'Thumbnail' => $this->getThumbnailDefinition(),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media1, $media2], $media);
    }

    public function testGetNotUsedMediaAppliesFolderRestrictionToCriteriaIfPresent(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(1, $filters);

                    self::assertInstanceOf(EqualsAnyFilter::class, $filters[0]);
                    self::assertEquals('media.mediaFolder.id', $filters[0]->getField());
                    self::assertEquals(['id1', 'id2', 'id3', 'id4'], $filters[0]->getValue());

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                [],
            ],
            $mediaDefinition
        );

        $connection = $this->createMock(Connection::class);

        $connection
            ->method('fetchOne')
            ->willReturn('id1');

        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturn([
                'id2' => ['id' => 'id2', 'parent_id' => 'id1'],
                'id3' => ['id' => 'id3', 'parent_id' => 'id2'],
                'id4' => ['id' => 'id4', 'parent_id' => 'id1'],
                'id5' => ['id' => 'id4', 'parent_id' => 'some-other-parent'],
                'id6' => ['id' => 'id4', 'parent_id' => 'id5'],
            ]);

        $purger = new UnusedMediaPurger($repo, $connection, new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia(null, null, null, 'media_gallery')));

        static::assertEquals([$media1, $media2], $media);
    }

    public function testGetNotUsedMediaSkipsAssociationIfNoFkeyFound(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                new OneToManyAssociationField('productMedia', 'ProductMedia', 'media_id', 'id'),
            ]),
            'ProductMedia' => $this->getProductMediaDefinition(false),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media1, $media2], $media);
    }

    public function testGetNotUsedMediaSkipsNonValidAssociations(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
            ]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id1, $id2, $media1, $media2) {
                    static::assertSame([$id1, $id2], $criteria->getIds());

                    return new MediaCollection([$media1, $media2]);
                },
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media1, $media2], $media);
    }

    public function testGetNotUsedMediaDoesNotFetchMediaIfRemovedByListener(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id2, $media2) {
                    static::assertSame([$id2], $criteria->getIds());

                    return new MediaCollection([$media2]);
                },
                [],
            ],
            $mediaDefinition
        );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(UnusedMediaSearchEvent::class, function (UnusedMediaSearchEvent $event) use ($id1): void {
            $event->markAsUsed([$id1]);
        });

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), $eventDispatcher);
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([$media2], $media);
    }

    public function testGetNotUsedMediaDoesNotFetchAnyMediaIfAllRemovedByListener(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(UnusedMediaSearchEvent::class, function (UnusedMediaSearchEvent $event) use ($id1, $id2): void {
            $event->markAsUsed([$id1, $id2]);
        });

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), $eventDispatcher);
        $media = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));

        static::assertEquals([], $media);
    }

    public function testDeleteNotUsedMediaOnlyAppliesValidAssociationsToCriteria(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                [$id1, $id2],
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $media1->getId()],
                    ['id' => $media2->getId()],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaWithPaging(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();
        $id4 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);
        $media2 = $this->createMedia($id2);
        $media3 = $this->createMedia($id3);
        $media4 = $this->createMedia($id4);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query

                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);
                    self::assertEquals(0, $criteria->getOffset());
                    self::assertEquals(50, $criteria->getLimit());

                    return [$id1, $id2];
                },
                function (Criteria $criteria, Context $context) use ($id3, $id4) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);
                    self::assertEquals(50, $criteria->getOffset());
                    self::assertEquals(50, $criteria->getLimit());

                    return [$id3, $id4];
                },
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $media1->getId()],
                    ['id' => $media2->getId()],
                    ['id' => $media3->getId()],
                    ['id' => $media4->getId()],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaOnlyDeletesSingleResultSetIfLimitAndOffsetSupplied(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();
        $id4 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query

                function (Criteria $criteria, Context $context) use ($id1, $id2, $id3, $id4) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2, $id3, $id4];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia(4, 0);

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                    ['id' => $id3],
                    ['id' => $id4],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaCorrectlyAppliesOneToOneAssociationToCriteria(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                (new FkField('meta_id', 'metaId', 'Meta'))->addFlags(new Required()),
                new OneToOneAssociationField('meta', 'meta_id', 'id', 'Meta', false),
            ]),
            'Meta' => $this->getMetaDefinition(),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(1, $filters);

                    self::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    self::assertEquals('media.meta.id', $filters[0]->getField());
                    self::assertNull($filters[0]->getValue());

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaCorrectlyAppliesOneToManyAssociationToCriteria(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                new OneToManyAssociationField('productMedia', 'ProductMedia', 'media_id', 'id'),
            ]),
            'ProductMedia' => $this->getProductMediaDefinition(),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(1, $filters);

                    self::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    self::assertEquals('media.productMedia.mediaId', $filters[0]->getField());
                    self::assertNull($filters[0]->getValue());

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaCorrectlyAppliesManyToManyAssociationToCriteria(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                new ManyToManyAssociationField(
                    'galleries',
                    'MediaGallery',
                    'MediaGalleryMapping',
                    'media_id',
                    'gallery_id',
                ),
            ]),
            'MediaGallery' => $this->getMediaGalleryDefinition(),
            'MediaGalleryMapping' => $this->getMediaGalleryMappingDefinition(),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(1, $filters);

                    self::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    self::assertEquals('media.galleries.id', $filters[0]->getField());
                    self::assertNull($filters[0]->getValue());

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaIgnoresAggregateEntities(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                (new OneToManyAssociationField('thumbnails', 'Thumbnail', 'media_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            ]),
            'Thumbnail' => $this->getThumbnailDefinition(),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaThrowsExceptionWithInvalidFolderRestriction(): void
    {
        static::expectException(MediaException::class);
        static::expectExceptionMessage('Could not find a default folder with entity "product"');

        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia(null, null, null, 'product');

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaAppliesFolderRestrictionToCriteriaIfPresent(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(1, $filters);

                    self::assertInstanceOf(EqualsAnyFilter::class, $filters[0]);
                    self::assertEquals('media.mediaFolder.id', $filters[0]->getField());
                    self::assertEquals(['id1', 'id2', 'id3', 'id4'], $filters[0]->getValue());

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $connection = $this->createMock(Connection::class);

        $connection
            ->method('fetchOne')
            ->willReturn('id1');

        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturn([
                'id2' => ['id' => 'id2', 'parent_id' => 'id1'],
                'id3' => ['id' => 'id3', 'parent_id' => 'id2'],
                'id4' => ['id' => 'id4', 'parent_id' => 'id1'],
                'id5' => ['id' => 'id4', 'parent_id' => 'some-other-parent'],
                'id6' => ['id' => 'id4', 'parent_id' => 'id5'],
            ]);

        $purger = new UnusedMediaPurger($repo, $connection, new EventDispatcher());
        $purger->deleteNotUsedMedia(null, null, null, 'media_gallery');

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaSkipsAssociationIfNoFkeyFound(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                new OneToManyAssociationField('productMedia', 'ProductMedia', 'media_id', 'id'),
            ]),
            'ProductMedia' => $this->getProductMediaDefinition(false),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaSkipsNonValidAssociations(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([
                new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
            ]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $id1],
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaDoesNotDeleteMediaIfRemovedByListener(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(UnusedMediaSearchEvent::class, function (UnusedMediaSearchEvent $event) use ($id1): void {
            $event->markAsUsed([$id1]);
        });

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), $eventDispatcher);
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [
                [
                    ['id' => $id2],
                ],
            ],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaDoesNotDeleteAnyMediaIfAllRemovedByListener(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                function (Criteria $criteria, Context $context) use ($id1, $id2) {
                    $filters = $criteria->getFilters();

                    self::assertCount(0, $filters);

                    return [$id1, $id2];
                },
                // fake the grace period filter
                fn (Criteria $criteria) => $criteria->getIds(),
                [],
            ],
            $mediaDefinition
        );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(UnusedMediaSearchEvent::class, function (UnusedMediaSearchEvent $event) use ($id1, $id2): void {
            $event->markAsUsed([$id1, $id2]);
        });

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), $eventDispatcher);
        $purger->deleteNotUsedMedia();

        static::assertEquals(
            [],
            $repo->deletes
        );
    }

    public function testDeleteNotUsedMediaIgnoresMediaUploadedWithinTheGracePeriod(): void
    {
        $this->configureRegistry([
            'Media' => $mediaDefinition = $this->getMediaDefinition([]),
        ]);

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $media1 = $this->createMedia($id1);

        /** @var StaticEntityRepository<MediaCollection> $repo */
        $repo = new StaticEntityRepository(
            [
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 10, new MediaCollection(), null, $criteria, $context), // total media count query
                fn (Criteria $criteria, Context $context) => new EntitySearchResult('media', 2, new MediaCollection(), null, $criteria, $context), // purgable media count query
                [$id1, $id2],
                function (Criteria $criteria) use ($id1, $id2) {
                    static::assertEquals([$id1, $id2], $criteria->getIds());

                    return [$id1];
                },
                [],
            ],
            $mediaDefinition
        );

        $purger = new UnusedMediaPurger($repo, $this->createMock(Connection::class), new EventDispatcher());
        $purger->deleteNotUsedMedia(null, null, 3);

        static::assertEquals(
            [
                [
                    ['id' => $media1->getId()],
                ],
            ],
            $repo->deletes
        );
    }

    /**
     * @param array<EntityDefinition> $definitions
     */
    private function configureRegistry(array $definitions): void
    {
        new StaticDefinitionInstanceRegistry(
            $definitions,
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    /**
     * @param array<Field> $fields
     */
    private function getMediaDefinition(array $fields): EntityDefinition
    {
        $instance = new class extends EntityDefinition {
            /**
             * @var array<Field>
             */
            public array $extraFields = [];

            public function getEntityName(): string
            {
                return 'media';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                    (new StringField('file_extension', 'fileExtension'))->addFlags(new ApiAware()),

                    ...$this->extraFields,
                ]);
            }
        };
        $instance->extraFields = $fields;

        return $instance;
    }

    private function getProductMediaDefinition(bool $withFkey = true): EntityDefinition
    {
        $definition = new class extends EntityDefinition {
            public bool $withFkey;

            public function getEntityName(): string
            {
                return 'product_media';
            }

            protected function defineFields(): FieldCollection
            {
                $fields = new FieldCollection([
                    new ManyToOneAssociationField('media', 'media_id', 'Media', 'id'),
                ]);

                if ($this->withFkey) {
                    $fields->add(new FkField('media_id', 'mediaId', 'Media'));
                }

                return $fields;
            }
        };

        $definition->withFkey = $withFkey;

        return $definition;
    }

    private function getMetaDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'media_meta';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),

                    new OneToOneAssociationField('media', 'id', 'meta_id', 'Media', false),
                ]);
            }
        };
    }

    private function getMediaGalleryDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'media_gallery';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                    (new StringField('title', 'title'))->addFlags(new Required()),

                    new ManyToManyAssociationField(
                        'media',
                        'Media',
                        'MediaGalleryMapping',
                        'gallery_id',
                        'media_id'
                    ),
                ]);
            }
        };
    }

    private function getMediaGalleryMappingDefinition(): EntityDefinition
    {
        return new class extends MappingEntityDefinition {
            public function getEntityName(): string
            {
                return 'media_gallery_mapping';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new FkField('media_id', 'mediaId', 'Media'))->addFlags(new PrimaryKey(), new Required()),
                    (new FkField('gallery_id', 'galleryId', 'MediaGallery'))->addFlags(new PrimaryKey(), new Required()),
                    new ManyToOneAssociationField('media', 'media_id', 'Media', 'id'),
                    new ManyToOneAssociationField('galleries', 'gallery_id', 'MediaGallery', 'id'),
                ]);
            }
        };
    }

    private function getThumbnailDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'media_thumbnail';
            }

            protected function getParentDefinitionClass(): string
            {
                return 'Media';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                    new ManyToOneAssociationField('media', 'media_id', 'Media', 'id', false),
                    (new FkField('media_id', 'mediaId', 'Media'))->addFlags(new ApiAware(), new Required()),
                ]);
            }
        };
    }

    private function createMedia(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);

        return $media;
    }
}
