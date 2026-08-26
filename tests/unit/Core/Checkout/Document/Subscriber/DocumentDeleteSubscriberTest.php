<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Subscriber\DocumentDeleteSubscriber;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentDeleteSubscriber::class)]
class DocumentDeleteSubscriberTest extends TestCase
{
    public function testBeforeDeleteDeletesMediaFilesOnSuccess(): void
    {
        $documentId = Uuid::randomBytes();
        $mediaId = Uuid::randomHex();
        $mediaIdA11y = Uuid::randomHex();

        $document = (new DocumentEntity())->assign([
            'id' => $documentId,
            'documentMediaFileId' => $mediaId,
            'documentA11yMediaFileId' => $mediaIdA11y,
        ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $documentDefinition = new DocumentDefinition();
        $documentDefinition->compile($definitionInstanceRegistry);

        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([]), // dependency check with empty result
            new EntitySearchResult(
                DocumentEntity::class,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria([$documentId]),
                Context::createDefaultContext(),
            ),
        ], $documentDefinition);

        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile($definitionInstanceRegistry);

        $mediaRepository = new StaticEntityRepository(
            [],
            $mediaDefinition,
        );

        $subscriber = new DocumentDeleteSubscriber(
            $documentRepository,
            $mediaRepository,
        );

        $entityDeleteEvent = $this->createEntityDeleteEvent(
            $documentDefinition,
            $documentId
        );

        $subscriber->beforeDelete($entityDeleteEvent);
        $entityDeleteEvent->success();

        $deleted = $mediaRepository->deletes;
        static::assertCount(1, $deleted);
        static::assertCount(2, $deleted[0]);
        foreach ($deleted[0] as $mediaFile) {
            static::assertContains($mediaFile['id'], [$mediaId, $mediaIdA11y]);
        }
    }

    public function testBeforeDeleteDeletesDocumentV2FileMediaOnSuccess(): void
    {
        $documentId = Uuid::randomBytes();
        $pdfMediaId = Uuid::randomHex();
        $htmlMediaId = Uuid::randomHex();

        $pdfDocumentFile = (new DocumentFileEntity())->assign([
            'id' => Uuid::randomHex(),
            'documentId' => Uuid::fromBytesToHex($documentId),
            'documentFormat' => 'pdf',
            'mediaId' => $pdfMediaId,
        ]);
        $htmlDocumentFile = (new DocumentFileEntity())->assign([
            'id' => Uuid::randomHex(),
            'documentId' => Uuid::fromBytesToHex($documentId),
            'documentFormat' => 'html',
            'mediaId' => $htmlMediaId,
        ]);

        $document = (new DocumentEntity())->assign([
            'id' => $documentId,
            'documentFiles' => new DocumentFileCollection([$pdfDocumentFile, $htmlDocumentFile]),
        ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $documentDefinition = new DocumentDefinition();
        $documentDefinition->compile($definitionInstanceRegistry);

        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([]), // dependency check with empty result
            new EntitySearchResult(
                DocumentEntity::class,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria([$documentId]),
                Context::createDefaultContext(),
            ),
        ], $documentDefinition);

        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile($definitionInstanceRegistry);

        $mediaRepository = new StaticEntityRepository(
            [],
            $mediaDefinition,
        );

        $subscriber = new DocumentDeleteSubscriber(
            $documentRepository,
            $mediaRepository,
        );

        $entityDeleteEvent = $this->createEntityDeleteEvent(
            $documentDefinition,
            $documentId
        );

        $subscriber->beforeDelete($entityDeleteEvent);
        $entityDeleteEvent->success();

        $deleted = $mediaRepository->deletes;
        static::assertCount(1, $deleted);
        static::assertCount(2, $deleted[0]);
        foreach ($deleted[0] as $mediaFile) {
            static::assertContains($mediaFile['id'], [$pdfMediaId, $htmlMediaId]);
        }
    }

    public function testBeforeDeleteShouldThrowExceptionWhenDependenciesOnOtherDocumentsExists(): void
    {
        $documentId = Uuid::randomBytes();
        $dependingDocumentId = Uuid::randomBytes();
        $dependingDocumentNumber = '10001';

        $documentType = (new DocumentTypeEntity())->assign([
            'id' => Uuid::randomBytes(),
            'technicalName' => CreditNoteRenderer::TYPE,
        ]);

        $dependingDocument = (new DocumentEntity())->assign([
            'id' => $dependingDocumentId,
            'referencedDocumentId' => $documentId,
            'documentNumber' => $dependingDocumentNumber,
            'documentType' => $documentType,
        ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $documentDefinition = new DocumentDefinition();
        $documentDefinition->compile($definitionInstanceRegistry);

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentEntity::class,
                1,
                new DocumentCollection([$dependingDocument]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ], $documentDefinition);

        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile($definitionInstanceRegistry);

        $mediaRepository = new StaticEntityRepository(
            [],
            $mediaDefinition,
        );

        $subscriber = new DocumentDeleteSubscriber(
            $documentRepository,
            $mediaRepository,
        );

        $entityDeleteEvent = $this->createEntityDeleteEvent(
            $documentDefinition,
            $documentId
        );

        $this->expectExceptionObject(DocumentException::documentHasDependentDocuments(
            [
                \sprintf(
                    '%s %s (%s)',
                    CreditNoteRenderer::TYPE,
                    $dependingDocumentNumber,
                    $dependingDocumentId,
                ),
            ]
        ));
        $subscriber->beforeDelete($entityDeleteEvent);
    }

    private function createEntityDeleteEvent(
        DocumentDefinition $documentDefinition,
        string $documentId
    ): EntityDeleteEvent {
        return EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $documentDefinition,
                    ['id' => $documentId],
                    new EntityExistence(
                        DocumentDefinition::ENTITY_NAME,
                        ['id' => $documentId],
                        true,
                        false,
                        false,
                        [
                            'exists' => true,
                            'id' => $documentId,
                        ],
                    )
                ),
            ]
        );
    }
}
