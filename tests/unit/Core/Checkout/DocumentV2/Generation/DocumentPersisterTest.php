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
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
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

    private string $renderedOrderVersionId;

    private RenderInput $renderInput;

    private RenderState $renderState;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $this->generationRequest = new DocumentGenerationRequest(
            Uuid::randomHex(),
            self::DOCUMENT_TYPE,
            [self::FORMAT],
            '12345',
        );

        $this->renderedOrderVersionId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setVersionId($this->renderedOrderVersionId);

        $this->renderInput = new RenderInput(
            self::DOCUMENT_TYPE,
            '12345',
            $order,
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

        $resolvedReference = new ReferencedDocument(
            id: Uuid::randomHex(),
            documentNumber: '1000',
            orderVersionId: Uuid::randomHex(),
        );

        $document = $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            $resolvedReference,
            $this->context,
        );

        static::assertCount(1, $documentRepository->creates);
        static::assertSame($documentRepository->creates[0][0]['id'], $document->getId());
        static::assertSame($documentTypeId, $documentRepository->creates[0][0]['documentTypeId']);
        static::assertSame($this->renderedOrderVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertSame($resolvedReference->id, $documentRepository->creates[0][0]['referencedDocumentId']);

        static::assertCount(1, $documentFileRepository->creates);
        static::assertSame([self::FORMAT], array_column($documentFileRepository->creates[0], 'documentFormat'));
        static::assertSame($fileId, $documentFileRepository->creates[0][0]['mediaId']);
        static::assertNull($documentRepository->creates[0][0]['documentA11yMediaFileId']);
    }

    public function testPersistAddsDependencyRenderedHtmlAsAccessibleVersion(): void
    {
        $pdfMediaId = Uuid::randomHex();
        $htmlMediaId = Uuid::randomHex();

        $this->renderState->add(new RenderResult(
            DocumentFormat::HTML->value,
            '<html lang="en">content</html>',
            'filename',
            'html',
            'text/html',
        ));

        $mediaService = static::createMock(MediaService::class);
        $mediaService->method('saveFile')
            ->willReturnCallback(static fn (string $content, string $extension) => $extension === 'html' ? $htmlMediaId : $pdfMediaId);

        [$persister, $documentRepository, $documentFileRepository] = $this->createPersister(
            Uuid::randomHex(),
            mediaService: $mediaService,
        );

        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            null,
            $this->context,
        );

        static::assertSame($pdfMediaId, $documentRepository->creates[0][0]['documentMediaFileId']);
        static::assertSame($htmlMediaId, $documentRepository->creates[0][0]['documentA11yMediaFileId']);

        $formats = array_column($documentFileRepository->creates[0], 'documentFormat');
        static::assertSame([self::FORMAT, DocumentFormat::HTML->value], $formats);
    }

    public function testPersistNeverUsesHtmlAsPrimaryMediaFile(): void
    {
        $htmlMediaId = Uuid::randomHex();

        $renderState = new RenderState();
        $renderState->add(new RenderResult(
            DocumentFormat::HTML->value,
            '<html lang="en">content</html>',
            'filename',
            'html',
            'text/html',
        ));

        [$persister, $documentRepository, $documentFileRepository] = $this->createPersister(
            Uuid::randomHex(),
            mediaServiceReturn: $htmlMediaId,
        );

        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $renderState,
            [DocumentFormat::HTML->value],
            null,
            $this->context,
        );

        static::assertNull($documentRepository->creates[0][0]['documentMediaFileId']);
        static::assertSame($htmlMediaId, $documentRepository->creates[0][0]['documentA11yMediaFileId']);

        $formats = array_column($documentFileRepository->creates[0], 'documentFormat');
        static::assertSame([DocumentFormat::HTML->value], $formats);
    }

    public function testPersistUsesFileNameProviderResolvedName(): void
    {
        $fileNameProvider = static::createMock(FileNameProvider::class);
        $fileNameProvider->expects($this->once())
            ->method('provide')
            ->with('filename', 'pdf', null, static::anything())
            ->willReturn('filename_(1)');

        $mediaService = static::createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('saveFile')
            ->with(
                static::anything(),
                static::anything(),
                static::anything(),
                'filename_(1)',
                static::anything(),
                static::anything(),
            )
            ->willReturn(Uuid::randomHex());

        [$persister] = $this->createPersister(
            Uuid::randomHex(),
            mediaService: $mediaService,
            fileNameProvider: $fileNameProvider,
        );

        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            null,
            $this->context,
        );
    }

    #[DataProvider('persistExceptionProvider')]
    public function testPersistThrowsException(
        ?callable $documentSearch,
        string $documentTypeId,
        DocumentV2Exception $exception,
    ): void {
        [$persister] = $this->createPersister($documentTypeId, $documentSearch);

        $this->expectExceptionObject($exception);

        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            null,
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

        $this->expectExceptionObject(DocumentV2Exception::documentNumberAlreadyExists('12345'));

        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            null,
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
        ?MediaService $mediaService = null,
        ?FileNameProvider $fileNameProvider = null,
    ): array {
        $documentRepository = StaticEntityRepository::of(DocumentCollection::class, [
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

        $documentFileRepository = StaticEntityRepository::of(DocumentFileCollection::class, [
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        $documentTypeRepository = StaticEntityRepository::of(DocumentTypeCollection::class, [
            static function (Criteria $criteria) use ($documentTypeId): array {
                static::assertSame(1, $criteria->getLimit());

                if ($documentTypeId === '') {
                    return [];
                }

                return [$documentTypeId];
            },
        ], new DocumentTypeDefinition());

        if ($mediaService === null) {
            $mediaService = static::createStub(MediaService::class);
            $mediaService->method('saveFile')->willReturn($mediaServiceReturn ?? Uuid::randomHex());
        }

        if ($fileNameProvider === null) {
            $fileNameProvider = static::createStub(FileNameProvider::class);
            $fileNameProvider->method('provide')->willReturnArgument(0);
        }

        return [
            new DocumentPersister(
                $documentRepository,
                $documentFileRepository,
                $documentTypeRepository,
                $mediaService,
                $fileNameProvider,
            ),
            $documentRepository,
            $documentFileRepository,
        ];
    }
}
