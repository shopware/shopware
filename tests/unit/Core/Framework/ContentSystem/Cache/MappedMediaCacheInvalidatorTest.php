<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\ContentSystem\Cache\MappedMediaCacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappedMediaCacheInvalidator::class)]
class MappedMediaCacheInvalidatorTest extends TestCase
{
    public function testMixedMediaAndTranslationWritesInvalidateEachMediaOnlyOnce(): void
    {
        $cache = $this->createMock(CacheInvalidator::class);
        $cache->expects($this->once())->method('invalidate')->with([
            MappedMediaCacheInvalidator::buildName('media-a'),
            MappedMediaCacheInvalidator::buildName('media-b'),
        ]);
        $event = EntityWrittenContainerEvent::createWithWrittenEvents([
            'media' => [new EntityWriteResult('media-a', [], 'media', EntityWriteResult::OPERATION_UPDATE)],
            'media_translation' => [
                new EntityWriteResult(['mediaId' => 'media-a', 'languageId' => 'en'], [], 'media_translation', EntityWriteResult::OPERATION_UPDATE),
                new EntityWriteResult(['mediaId' => 'media-b', 'languageId' => 'en'], [], 'media_translation', EntityWriteResult::OPERATION_UPDATE),
                new EntityWriteResult(['mediaId' => 'media-b', 'languageId' => 'de'], [], 'media_translation', EntityWriteResult::OPERATION_UPDATE),
            ],
        ], Context::createDefaultContext(), []);

        (new MappedMediaCacheInvalidator($cache))->invalidateWritten($event);
    }

    /**
     * @param string|array<string, string> $primaryKey
     */
    #[DataProvider('deletions')]
    public function testDeletionUsesThePrimaryKeyWithoutNeedingTheDeletedRow(string $entity, string|array $primaryKey): void
    {
        $cache = $this->createMock(CacheInvalidator::class);
        $cache->expects($this->once())->method('invalidate')->with([MappedMediaCacheInvalidator::buildName('media-a')]);
        $event = EntityWrittenContainerEvent::createWithDeletedEvents([
            $entity => [new EntityWriteResult($primaryKey, [], $entity, EntityWriteResult::OPERATION_DELETE)],
        ], Context::createDefaultContext(), []);

        (new MappedMediaCacheInvalidator($cache))->invalidateWritten($event);
    }

    /**
     * @return iterable<string, array{string, string|array<string, string>}>
     */
    public static function deletions(): iterable
    {
        yield 'media deletion' => ['media', 'media-a'];
        yield 'translation deletion' => ['media_translation', ['mediaId' => 'media-a', 'languageId' => 'en']];
    }

    public function testUnrelatedEntityWritesDoNotInvalidateAnything(): void
    {
        $cache = $this->createMock(CacheInvalidator::class);
        $cache->expects($this->never())->method('invalidate');
        $event = EntityWrittenContainerEvent::createWithWrittenEvents([
            'custom_field' => [new EntityWriteResult('field-a', [], 'custom_field', EntityWriteResult::OPERATION_UPDATE)],
        ], Context::createDefaultContext(), []);

        (new MappedMediaCacheInvalidator($cache))->invalidateWritten($event);
    }

    public function testIndexingInvalidatesOnlyItsMediaIds(): void
    {
        $cache = $this->createMock(CacheInvalidator::class);
        $cache->expects($this->once())->method('invalidate')->with([MappedMediaCacheInvalidator::buildName('media-a')]);

        (new MappedMediaCacheInvalidator($cache))->invalidateIndexed(new MediaIndexerEvent(['media-a', 'media-a'], Context::createDefaultContext()));
    }

    public function testEmptyIndexingDoesNotInvalidateAnything(): void
    {
        $cache = $this->createMock(CacheInvalidator::class);
        $cache->expects($this->never())->method('invalidate');

        (new MappedMediaCacheInvalidator($cache))->invalidateIndexed(new MediaIndexerEvent([], Context::createDefaultContext()));
    }
}
