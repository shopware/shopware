<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationEntity;
use Shopware\Core\Content\Product\Subscriber\ProductDocumentMediaSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentMediaSubscriber::class)]
class ProductDocumentMediaSubscriberTest extends TestCase
{
    private const GERMAN = 'dededededededededededededededede';

    private const SWISS_GERMAN = 'dcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdc';

    /**
     * @param non-empty-list<string> $languageChain
     */
    #[DataProvider('fileLanguageProvider')]
    public function testTheFileGetsTheLanguageItComesFrom(array $languageChain, bool $ownFile, ?bool $parentHasFile, string $expectedLanguage): void
    {
        $document = self::document(id: 'manual', file: 'file', ownFile: $ownFile);
        $translationRepository = StaticEntityRepository::of(
            ProductDocumentTranslationCollection::class,
            $parentHasFile === null ? [] : [new ProductDocumentTranslationCollection([self::translation(documentId: 'manual', mediaId: $parentHasFile ? 'file' : null)])],
        );

        $this->dispatch([$document], new StaticEntityRepository([new MediaCollection([self::media('file')])]), $translationRepository, $languageChain);

        static::assertSame('file', $document->getMedia()?->getId());
        static::assertSame($expectedLanguage, $document->getMediaLanguageId());
        static::assertCount($parentHasFile === null ? 0 : 1, $translationRepository->searchedCriteria);
    }

    public static function fileLanguageProvider(): \Generator
    {
        yield 'the requested language\'s own file' => [[self::GERMAN, Defaults::LANGUAGE_SYSTEM], true, null, self::GERMAN];
        yield 'the file of the default language' => [[self::GERMAN, Defaults::LANGUAGE_SYSTEM], false, null, Defaults::LANGUAGE_SYSTEM];
        yield 'the own file of a language with a parent' => [[self::SWISS_GERMAN, self::GERMAN, Defaults::LANGUAGE_SYSTEM], true, null, self::SWISS_GERMAN];
        yield 'the file of the parent language' => [[self::SWISS_GERMAN, self::GERMAN, Defaults::LANGUAGE_SYSTEM], false, true, self::GERMAN];
        yield 'the file of the default language when the parent has none' => [[self::SWISS_GERMAN, self::GERMAN, Defaults::LANGUAGE_SYSTEM], false, false, Defaults::LANGUAGE_SYSTEM];
        yield 'the file of the default language when it is also the parent' => [[self::SWISS_GERMAN, Defaults::LANGUAGE_SYSTEM, Defaults::LANGUAGE_SYSTEM], false, null, Defaults::LANGUAGE_SYSTEM];
    }

    public function testOneSearchLoadsThePublicFilesOfAllDocuments(): void
    {
        $manual = self::document(id: 'manual', file: 'file-a', ownFile: true);
        $datasheet = self::document(id: 'datasheet', file: 'file-b', ownFile: true);
        $certificate = self::document(id: 'certificate', file: 'file-a', ownFile: true);
        $mediaRepository = new StaticEntityRepository([new MediaCollection([self::media('file-a'), self::media('file-b')])]);

        $this->dispatch([$manual, $datasheet, $certificate], $mediaRepository, StaticEntityRepository::of(ProductDocumentTranslationCollection::class), [self::GERMAN, Defaults::LANGUAGE_SYSTEM]);

        static::assertCount(1, $mediaRepository->searchedCriteria);
        static::assertSame(['file-a', 'file-b'], $mediaRepository->searchedCriteria[0]->getIds());
        static::assertEquals([new EqualsFilter('private', false)], $mediaRepository->searchedCriteria[0]->getFilters());
        static::assertSame(['file-a', 'file-b', 'file-a'], [$manual->getMedia()?->getId(), $datasheet->getMedia()?->getId(), $certificate->getMedia()?->getId()]);
    }

    public function testOnlyDocumentsWithoutAnOwnFileReadTheParentLanguage(): void
    {
        $versionId = Uuid::randomHex();
        $translationRepository = new StaticEntityRepository([new ProductDocumentTranslationCollection()]);

        $this->dispatch(
            [self::document(id: 'manual', file: 'file-a', ownFile: true), self::document(id: 'datasheet', file: 'file-b', ownFile: false)],
            new StaticEntityRepository([new MediaCollection([self::media('file-a'), self::media('file-b')])]),
            $translationRepository,
            [self::SWISS_GERMAN, self::GERMAN, Defaults::LANGUAGE_SYSTEM],
            $versionId,
        );

        static::assertCount(1, $translationRepository->searchedCriteria);
        static::assertEquals([
            new EqualsAnyFilter('productDocumentId', ['datasheet']),
            new EqualsFilter('productDocumentVersionId', $versionId),
            new EqualsFilter('languageId', self::GERMAN),
        ], $translationRepository->searchedCriteria[0]->getFilters());
    }

    public function testADocumentWhoseFileIsNotFoundStaysWithoutFile(): void
    {
        $document = self::document(id: 'manual', file: 'missing-file', ownFile: true);

        $this->dispatch([$document], new StaticEntityRepository([new MediaCollection()]), StaticEntityRepository::of(ProductDocumentTranslationCollection::class), [Defaults::LANGUAGE_SYSTEM]);

        static::assertNull($document->getMedia());
        static::assertNull($document->getMediaLanguageId());
    }

    public function testNothingIsLoadedWithoutFiles(): void
    {
        $mediaRepository = StaticEntityRepository::of(MediaCollection::class);

        $this->dispatch(
            [self::document(id: 'manual', file: null, ownFile: false)],
            $mediaRepository,
            StaticEntityRepository::of(ProductDocumentTranslationCollection::class),
            [Defaults::LANGUAGE_SYSTEM],
        );

        static::assertSame([], $mediaRepository->searchedCriteria);
    }

    /**
     * @param list<ProductDocumentEntity> $documents
     * @param StaticEntityRepository<MediaCollection> $mediaRepository
     * @param StaticEntityRepository<ProductDocumentTranslationCollection> $translationRepository
     * @param non-empty-list<string> $languageChain
     */
    private function dispatch(
        array $documents,
        StaticEntityRepository $mediaRepository,
        StaticEntityRepository $translationRepository,
        array $languageChain,
        string $versionId = Defaults::LIVE_VERSION,
    ): void {
        $context = Generator::generateSalesChannelContext(
            baseContext: new Context(new SystemSource(), languageIdChain: $languageChain, versionId: $versionId),
        );

        (new ProductDocumentMediaSubscriber($mediaRepository, $translationRepository))->addMedia(
            new SalesChannelEntityLoadedEvent(new ProductDocumentDefinition(), $documents, $context),
        );
    }

    /**
     * The DAL puts the resolved file into `translated` and the requested language's own file into `mediaId`.
     */
    private static function document(string $id, ?string $file, bool $ownFile): ProductDocumentEntity
    {
        $document = new ProductDocumentEntity();
        $document->setId($id);
        $document->setMediaId($ownFile ? $file : null);
        $document->addTranslated('mediaId', $file);

        return $document;
    }

    private static function translation(string $documentId, ?string $mediaId): ProductDocumentTranslationEntity
    {
        $translation = new ProductDocumentTranslationEntity();
        $translation->setUniqueIdentifier($documentId . '-' . self::GERMAN);
        $translation->setProductDocumentId($documentId);
        $translation->setLanguageId(self::GERMAN);
        $translation->setMediaId($mediaId);

        return $translation;
    }

    private static function media(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);

        return $media;
    }
}
