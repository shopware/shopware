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
use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Event\DocumentGeneratedEvent;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticRenderData;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        $order->setSalesChannelId(Uuid::randomHex());

        $this->renderInput = new RenderInput(
            self::DOCUMENT_TYPE,
            '12345',
            $order,
            [
                'test' => new StaticRenderData(),
                DocumentMetaProvider::KEY => $this->createMetaRenderData(),
            ],
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
        static::assertSame(self::FORMAT, $documentFileRepository->creates[0][0]['documentFormat']);
        static::assertSame($fileId, $documentFileRepository->creates[0][0]['mediaId']);
    }

    public function testPersistDefaultsDisplayInCustomerAccountToFalseWhenNotConfigured(): void
    {
        [$persister, $documentRepository] = $this->createPersister(Uuid::randomHex());
        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            null,
            $this->context,
        );

        static::assertFalse($documentRepository->creates[0][0]['config']['displayInCustomerAccount']);
    }

    public function testPersistDispatchesDocumentGeneratedEvent(): void
    {
        $documentTypeId = Uuid::randomHex();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(DocumentGeneratedEvent::class))
            ->willReturnCallback(function (DocumentGeneratedEvent $event) {
                static::assertNotSame('', $event->documentId);
                static::assertSame($this->generationRequest->orderId, $event->orderId);
                static::assertSame($this->renderInput->order->getVersionId(), $event->orderVersionId);
                static::assertSame(self::DOCUMENT_TYPE, $event->documentType);
                static::assertSame($this->renderInput->documentNumber, $event->documentNumber);

                return $event;
            });

        [$persister] = $this->createPersister($documentTypeId, eventDispatcher: $eventDispatcher);

        $persister->persist(
            $this->generationRequest,
            $this->renderInput,
            $this->renderState,
            [self::FORMAT],
            null,
            $this->context,
        );
    }

    public function testPersistCarriesDisplayInCustomerAccountFromTheDocumentTypeConfig(): void
    {
        [$persister, $documentRepository] = $this->createPersister(Uuid::randomHex());

        $renderInput = new RenderInput(
            self::DOCUMENT_TYPE,
            '12345',
            $this->renderInput->order,
            [
                'test' => new StaticRenderData(),
                DocumentMetaProvider::KEY => $this->createMetaRenderData(legacyConfig: ['displayInCustomerAccount' => true]),
            ],
        );

        $persister->persist(
            $this->generationRequest,
            $renderInput,
            $this->renderState,
            [self::FORMAT],
            null,
            $this->context,
        );

        static::assertTrue($documentRepository->creates[0][0]['config']['displayInCustomerAccount']);
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

    public function testPersistUploaded(): void
    {
        $documentTypeId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $documentRepository = StaticEntityRepository::of(DocumentCollection::class, [
            static function (
                Criteria $criteria,
                Context $context,
                StaticEntityRepository $repository,
            ): DocumentCollection {
                $document = new DocumentEntity();
                $document->setId($repository->creates[0][0]['id']);
                $document->setOrderId($repository->creates[0][0]['orderId']);
                $document->setOrderVersionId($repository->creates[0][0]['orderVersionId']);
                $document->setStatic(true);

                return new DocumentCollection([$document]);
            },
        ], new DocumentDefinition());

        $documentFileRepository = StaticEntityRepository::of(DocumentFileCollection::class, [
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        $documentTypeRepository = StaticEntityRepository::of(DocumentTypeCollection::class, [
            [$documentTypeId],
        ], new DocumentTypeDefinition());

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(DocumentGeneratedEvent::class))
            ->willReturnCallback(static function (DocumentGeneratedEvent $event) use ($orderId, $orderVersionId, $documentRepository) {
                static::assertSame($orderId, $event->orderId);
                static::assertSame($orderVersionId, $event->orderVersionId);
                static::assertSame($documentRepository->creates[0][0]['id'], $event->documentId);
                static::assertSame(self::DOCUMENT_TYPE, $event->documentType);
                static::assertSame('12345', $event->documentNumber);

                return $event;
            });

        $persister = new DocumentPersister(
            $documentRepository,
            $documentFileRepository,
            $documentTypeRepository,
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $eventDispatcher,
        );

        $document = $persister->persistUploaded(
            self::DOCUMENT_TYPE,
            $orderId,
            $orderVersionId,
            '12345',
            self::FORMAT,
            $mediaId,
            null,
            $this->context,
        );

        static::assertCount(1, $documentRepository->creates);
        static::assertSame($documentRepository->creates[0][0]['id'], $document->getId());
        static::assertSame($orderId, $documentRepository->creates[0][0]['orderId']);
        static::assertSame($orderVersionId, $documentRepository->creates[0][0]['orderVersionId']);
        static::assertSame($documentTypeId, $documentRepository->creates[0][0]['documentTypeId']);
        static::assertSame($mediaId, $documentRepository->creates[0][0]['documentMediaFileId']);
        static::assertTrue($documentRepository->creates[0][0]['static']);

        static::assertCount(1, $documentFileRepository->creates);
        static::assertSame(self::FORMAT, $documentFileRepository->creates[0][0]['documentFormat']);
        static::assertSame($mediaId, $documentFileRepository->creates[0][0]['mediaId']);
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

    public function testPersistUniquenessCheckFiltersByDocumentNumberAndTypeName(): void
    {
        $documentTypeId = Uuid::randomHex();

        $documentRepository = StaticEntityRepository::of(DocumentCollection::class, [
            static function (Criteria $criteria): array {
                $filters = $criteria->getFilters();

                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertSame('documentNumber', $filters[0]->getField());
                static::assertSame('12345', $filters[0]->getValue());

                static::assertInstanceOf(EqualsFilter::class, $filters[1]);
                static::assertSame('typeName', $filters[1]->getField());
                static::assertSame(self::DOCUMENT_TYPE, $filters[1]->getValue());

                return [];
            },
            static function (
                Criteria $criteria,
                Context $context,
                StaticEntityRepository $repository,
            ): DocumentCollection {
                $document = new DocumentEntity();
                $document->setId($repository->creates[0][0]['id']);
                $document->setOrderId($repository->creates[0][0]['orderId']);
                $document->setOrderVersionId($repository->creates[0][0]['orderVersionId']);

                return new DocumentCollection([$document]);
            },
        ], new DocumentDefinition());

        $documentFileRepository = StaticEntityRepository::of(DocumentFileCollection::class, [
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        $documentTypeRepository = StaticEntityRepository::of(DocumentTypeCollection::class, [
            [$documentTypeId],
        ], new DocumentTypeDefinition());

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturn(Uuid::randomHex());

        $persister = new DocumentPersister(
            $documentRepository,
            $documentFileRepository,
            $documentTypeRepository,
            $mediaService,
            static::createStub(FileNameProvider::class),
            static::createStub(EventDispatcherInterface::class),
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
        ?EventDispatcherInterface $eventDispatcher = null,
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
                $document->setOrderId($repository->creates[0][0]['orderId']);
                $document->setOrderVersionId($repository->creates[0][0]['orderVersionId']);
                $document->setStatic(false);

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
                $eventDispatcher ?? static::createStub(EventDispatcherInterface::class),
            ),
            $documentRepository,
            $documentFileRepository,
        ];
    }

    /**
     * @param array<string, mixed> $legacyConfig
     */
    private function createMetaRenderData(array $legacyConfig = []): DocumentMetaRenderData
    {
        return new DocumentMetaRenderData(
            config: new DocumentConfig(
                pageSize: 'a4',
                pageOrientation: 'portrait',
                itemsPerPage: 10,
            ),
            company: new DocumentCompanyInfo(
                'Example',
                'Example Street 1',
                '12345',
                'Example City',
                new CountryEntity(),
            ),
            display: new DocumentDisplayOptions(),
            documentDate: '2024-01-01 00:00:00',
            documentNumber: '12345',
            documentComment: null,
            legacyConfig: $legacyConfig,
        );
    }
}
