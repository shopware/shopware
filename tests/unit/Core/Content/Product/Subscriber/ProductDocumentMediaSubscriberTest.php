<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Subscriber\ProductDocumentMediaSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentMediaSubscriber::class)]
class ProductDocumentMediaSubscriberTest extends TestCase
{
    public function testSubscribesToLoadedDocuments(): void
    {
        static::assertSame(
            ['product_document.loaded' => 'resolveMedia'],
            ProductDocumentMediaSubscriber::getSubscribedEvents()
        );
    }

    public function testResolvesTheFileOfTheLanguageChainInSystemScope(): void
    {
        $germanFile = $this->createMedia('german-file');
        $repository = StaticEntityRepository::of(MediaCollection::class, [
            static function (Criteria $criteria, Context $context) use ($germanFile): MediaCollection {
                static::assertSame(['german-file'], $criteria->getIds());
                static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());

                return new MediaCollection([$germanFile]);
            },
        ]);

        $document = $this->createDocument('document-id', ownMediaId: 'english-file', translatedMediaId: 'german-file');

        (new ProductDocumentMediaSubscriber($repository))->resolveMedia($this->createEvent([$document]));

        static::assertSame($germanFile, $document->getMedia());
    }

    public function testUsesTheStoredFileWhenNoTranslatedValueWasLoaded(): void
    {
        $file = $this->createMedia('own-file');
        $repository = StaticEntityRepository::of(MediaCollection::class, [new MediaCollection([$file])]);

        $document = $this->createDocument('document-id', ownMediaId: 'own-file', translatedMediaId: null);

        (new ProductDocumentMediaSubscriber($repository))->resolveMedia($this->createEvent([$document]));

        static::assertSame($file, $document->getMedia());
    }

    public function testLoadsTheFilesOfAllDocumentsInOneSearch(): void
    {
        $datasheet = $this->createMedia('datasheet-file');
        $manual = $this->createMedia('manual-file');
        $repository = StaticEntityRepository::of(MediaCollection::class, [
            static function (Criteria $criteria) use ($datasheet, $manual): MediaCollection {
                static::assertSame(['datasheet-file', 'manual-file'], $criteria->getIds());

                return new MediaCollection([$datasheet, $manual]);
            },
        ]);

        $first = $this->createDocument('datasheet', ownMediaId: null, translatedMediaId: 'datasheet-file');
        $second = $this->createDocument('manual', ownMediaId: null, translatedMediaId: 'manual-file');
        // a parent document shows up once per variant that inherits it
        $inherited = $this->createDocument('manual', ownMediaId: null, translatedMediaId: 'manual-file');

        (new ProductDocumentMediaSubscriber($repository))->resolveMedia($this->createEvent([$first, $second, $inherited]));

        static::assertSame($datasheet, $first->getMedia());
        static::assertSame($manual, $second->getMedia());
        static::assertSame($manual, $inherited->getMedia());
    }

    public function testDoesNotSearchWhenNothingIsLeftToResolve(): void
    {
        // a search would fail: the repository has no prepared results
        $repository = StaticEntityRepository::of(MediaCollection::class);

        $alreadyResolved = $this->createDocument('resolved', ownMediaId: 'file', translatedMediaId: 'file');
        $alreadyResolved->setMedia($this->createMedia('file'));
        $withoutFile = $this->createDocument('without-file', ownMediaId: null, translatedMediaId: null);

        (new ProductDocumentMediaSubscriber($repository))->resolveMedia($this->createEvent([$alreadyResolved, $withoutFile]));

        static::assertNull($withoutFile->getMedia());
    }

    public function testLeavesTheFileEmptyWhenTheMediaCannotBeFound(): void
    {
        $repository = StaticEntityRepository::of(MediaCollection::class, [new MediaCollection()]);

        $document = $this->createDocument('document-id', ownMediaId: null, translatedMediaId: 'deleted-file');

        (new ProductDocumentMediaSubscriber($repository))->resolveMedia($this->createEvent([$document]));

        static::assertNull($document->getMedia());
    }

    /**
     * @param list<ProductDocumentEntity> $documents
     *
     * @return EntityLoadedEvent<ProductDocumentEntity>
     */
    private function createEvent(array $documents): EntityLoadedEvent
    {
        return new EntityLoadedEvent(new ProductDocumentDefinition(), $documents, Context::createDefaultContext());
    }

    private function createDocument(string $id, ?string $ownMediaId, ?string $translatedMediaId): ProductDocumentEntity
    {
        $document = new ProductDocumentEntity();
        $document->setId($id);
        $document->setMediaId($ownMediaId);

        if ($translatedMediaId !== null) {
            $document->addTranslated('mediaId', $translatedMediaId);
        }

        return $document;
    }

    private function createMedia(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);

        return $media;
    }
}
