<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticRenderData;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentPersister::class)]
class DocumentPersisterTest extends TestCase
{
    private const DOCUMENT_TYPE = DocumentType::INVOICE->value;

    private const FORMAT = DocumentFormat::PDF->value;

    private DocumentGenerationRequest $generationRequest;

    private RenderInput $renderInput;

    private RenderState $renderState;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $this->generationRequest = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            self::DOCUMENT_TYPE,
            [self::FORMAT],
            '12345',
        );

        $this->renderInput = new RenderInput(
            self::DOCUMENT_TYPE,
            '12345',
            new OrderEntity(),
            ['test' => new StaticRenderData()]
        );

        $this->renderState = new RenderState();
        $this->renderState->add(new RenderResult(
            self::FORMAT,
            'content',
            'filename',
            'pdf',
            'application/pdf',
        ));
    }

    public function testPersist(): void
    {
        $fileId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();

        [$persister, $documentRepository, $documentFileRepository] = $this->createPersister(
            $documentTypeId,
            mediaServiceReturn: $fileId,
        );

        $document = $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            $this->context,
        );

        static::assertInstanceOf(DocumentEntity::class, $document);
        static::assertCount(1, $documentRepository->creates);
        static::assertSame($documentRepository->creates[0][0]['id'], $document->getId());
        static::assertSame($documentTypeId, $documentRepository->creates[0][0]['documentTypeId']);

        static::assertCount(1, $documentFileRepository->creates);
        static::assertSame(self::FORMAT, $documentFileRepository->creates[0][0]['documentFormat']);
        static::assertSame($fileId, $documentFileRepository->creates[0][0]['mediaId']);
    }

    #[DataProvider('persistExceptionProvider')]
    public function testPersistThrowsException(
        ?callable $documentSearch,
        string $documentTypeId,
        DocumentV2Exception $exception,
    ): void {
        [$persister] = $this->createPersister($documentTypeId, $documentSearch);

        static::expectExceptionObject($exception);

        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            $this->context,
        );
    }

    /**
     * @return iterable<string, array{
     *     documentSearch: ?callable,
     *     documentTypeId: string,
     *     exception: DocumentV2Exception,
     * }>
     */
    public static function persistExceptionProvider(): iterable
    {
        yield 'document not persisted' => [
            'documentSearch' => static function (
                Criteria $criteria,
                Context $context,
                StaticEntityRepository $repository,
            ): DocumentCollection {
                static::assertCount(1, $repository->creates);
                static::assertCount(1, $criteria->getIds());

                return new DocumentCollection([]);
            },
            'documentTypeId' => Uuid::randomHex(),
            'exception' => DocumentV2Exception::documentNotPersisted('12345'),
        ];

        yield 'document type not found' => [
            'documentSearch' => null,
            'documentTypeId' => '',
            'exception' => DocumentV2Exception::documentTypeNotFound(self::DOCUMENT_TYPE),
        ];
    }

    public function testPersistThrowsWhenDocumentNumberAlreadyExists(): void
    {
        $documentTypeId = Uuid::randomHex();
        $existingDocumentId = Uuid::randomHex();

        [$persister] = $this->createPersister($documentTypeId, existingDocumentIds: [$existingDocumentId]);

        static::expectExceptionObject(DocumentV2Exception::documentNumberAlreadyExists('12345'));

        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            $this->context,
        );
    }

    /**
     * @param list<string> $existingDocumentIds
     *
     * @return array{
     *     0: DocumentPersister,
     *     1: StaticEntityRepository<DocumentCollection>,
     *     2: StaticEntityRepository<DocumentFileCollection>,
     * }
     */
    private function createPersister(
        string $documentTypeId,
        ?callable $documentSearch = null,
        array $existingDocumentIds = [],
        ?string $mediaServiceReturn = null,
    ): array {
        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            $existingDocumentIds,
            $documentSearch ?? static function (
                Criteria $criteria,
                Context $context,
                StaticEntityRepository $repository,
            ): DocumentCollection {
                static::assertCount(1, $repository->creates);
                static::assertCount(1, $criteria->getIds());

                $document = new DocumentEntity();
                $document->setId($repository->creates[0][0]['id']);

                return new DocumentCollection([$document]);
            },
        ], new DocumentDefinition());

        /** @var StaticEntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = new StaticEntityRepository([
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        /** @var StaticEntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($documentTypeId): array {
                static::assertSame(1, $criteria->getLimit());

                if ($documentTypeId === '') {
                    return [];
                }

                return [$documentTypeId];
            },
        ], new DocumentTypeDefinition());

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->method('saveFile')->willReturn($mediaServiceReturn ?? Uuid::randomHex());

        return [
            new DocumentPersister(
                $documentRepository,
                $documentFileRepository,
                $documentTypeRepository,
                $mediaService,
            ),
            $documentRepository,
            $documentFileRepository,
        ];
    }
}
