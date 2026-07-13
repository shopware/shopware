<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\Controller\DocumentV2Controller;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentDataProvider;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentV2Controller::class)]
class DocumentV2ControllerTest extends TestCase
{
    public function testAvailableTypesReturnsFormatsForRendererSupportedDocumentTypes(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value, 'partial_cancellation']),
            new StaticDocumentRenderer(DocumentFormat::PDF, ['partial_cancellation']),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            new StaticEntityRepository([], new DocumentDefinition()),
            new StaticEntityRepository([], new DocumentFileDefinition()),
            new StaticEntityRepository([], new DocumentTypeDefinition()),
            static::createStub(MediaService::class),
        );

        $response = $controller->availableTypes();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            [
                'documentTypes' => [
                    DocumentType::INVOICE->value => [
                        'formats' => [
                            DocumentFormat::HTML->value,
                        ],
                    ],
                    'partial_cancellation' => [
                        'formats' => [
                            DocumentFormat::HTML->value,
                            DocumentFormat::PDF->value,
                        ],
                    ],
                ],
            ],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testCreateRejectsUnsupportedFormat(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        static::expectExceptionObject(
            DocumentV2Exception::rendererNotFound(DocumentFormat::PDF->value, DocumentType::INVOICE->value)
        );

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            new StaticEntityRepository([], new DocumentDefinition()),
            new StaticEntityRepository([], new DocumentFileDefinition()),
            new StaticEntityRepository([], new DocumentTypeDefinition()),
            static::createStub(MediaService::class),
        );

        $controller->create(
            new DocumentGenerationRequest('order-id', 'order-version-id', DocumentType::INVOICE, [DocumentFormat::PDF]),
            Context::createDefaultContext(),
        );
    }

    public function testCreateReturnsGeneratedDocumentResponse(): void
    {
        $orderId = Uuid::randomHex();

        $document = new DocumentEntity();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId, $document),
            $rendererRegistry,
            new StaticEntityRepository([], new DocumentDefinition()),
            new StaticEntityRepository([], new DocumentFileDefinition()),
            new StaticEntityRepository([], new DocumentTypeDefinition()),
            static::createStub(MediaService::class),
        );

        $response = $controller->create(
            new DocumentGenerationRequest(
                $orderId,
                Uuid::randomHex(),
                DocumentType::INVOICE,
                [DocumentFormat::HTML],
                '1000',
            ),
            Context::createDefaultContext(),
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            [
                'deepLinkCode' => $document->getDeepLinkCode(),
                'documentId' => $document->getId(),
                'fileTypes' => [
                    DocumentFormat::HTML->value,
                ],
            ],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testPreviewRejectsUnsupportedFormat(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        static::expectExceptionObject(
            DocumentV2Exception::rendererNotFound(DocumentFormat::PDF->value, DocumentType::INVOICE->value)
        );

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            new StaticEntityRepository([], new DocumentDefinition()),
            new StaticEntityRepository([], new DocumentFileDefinition()),
            new StaticEntityRepository([], new DocumentTypeDefinition()),
            static::createStub(MediaService::class),
        );

        $controller->preview(
            new DocumentGenerationRequest('order-id', 'order-version-id', DocumentType::INVOICE, [DocumentFormat::PDF]),
            Context::createDefaultContext(),
        );
    }

    public function testPreviewReturnsRenderedDocumentResponse(): void
    {
        $orderId = Uuid::randomHex();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            new StaticEntityRepository([], new DocumentDefinition()),
            new StaticEntityRepository([], new DocumentFileDefinition()),
            new StaticEntityRepository([], new DocumentTypeDefinition()),
            static::createStub(MediaService::class),
        );

        $response = $controller->preview(
            new DocumentGenerationRequest(
                $orderId,
                Uuid::randomHex(),
                DocumentType::INVOICE,
                [DocumentFormat::HTML],
                '1000',
            ),
            Context::createDefaultContext(),
        );

        static::assertSame('content', $response->getContent());
        static::assertSame(DocumentFormat::HTML->mimeType(), $response->headers->get('content-type'));
        static::assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('filename.html', (string) $response->headers->get('content-disposition'));
    }

    public function testUploadReturnsUploadedDocumentResponse(): void
    {
        $documentTypeId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([], new DocumentDefinition());

        /** @var StaticEntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = new StaticEntityRepository([], new DocumentFileDefinition());

        /** @var StaticEntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = new StaticEntityRepository([], new DocumentTypeDefinition());
        $documentTypeRepository->searches[] = [$documentTypeId];

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            $documentRepository,
            $documentFileRepository,
            $documentTypeRepository,
            static::createStub(MediaService::class),
        );

        $response = $controller->upload(
            Request::create(
                '/api/_action/order/document-v2/upload',
                Request::METHOD_POST,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
                    'documentComment' => '',
                    'documentDate' => '2026-07-13T00:00:00.000Z',
                    'documentNumber' => '1000',
                    'documentType' => DocumentType::INVOICE->value,
                    'fileType' => DocumentFormat::PDF->value,
                    'mediaId' => $mediaId,
                    'orderId' => $orderId,
                    'orderVersionId' => $orderVersionId,
                ], \JSON_THROW_ON_ERROR),
            ),
            Context::createDefaultContext(),
        );

        static::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($payload['documentId'] ?? null);
        static::assertIsString($payload['deepLinkCode'] ?? null);
        static::assertSame([DocumentFormat::PDF->value], $payload['fileTypes'] ?? null);

        static::assertSame([
            [
                'id' => $payload['documentId'],
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'documentTypeId' => $documentTypeId,
                'documentMediaFileId' => $mediaId,
                'referencedDocumentId' => null,
                'static' => true,
                'deepLinkCode' => $payload['deepLinkCode'],
                'config' => [
                    'documentComment' => '',
                    'documentDate' => '2026-07-13T00:00:00.000Z',
                    'documentNumber' => '1000',
                ],
            ],
        ], $documentRepository->creates[0]);
        static::assertSame($payload['documentId'], $documentFileRepository->creates[0][0]['documentId']);
        static::assertSame(DocumentFormat::PDF->value, $documentFileRepository->creates[0][0]['documentFormat']);
        static::assertSame($mediaId, $documentFileRepository->creates[0][0]['mediaId']);
    }

    public function testUploadStoresBinaryRequestWithQueryPayload(): void
    {
        $documentTypeId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();
        $content = 'uploaded invoice';
        $mediaFile = new MediaFile('invoice.pdf', DocumentFormat::PDF->mimeType(), DocumentFormat::PDF->value, \strlen($content));

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([], new DocumentDefinition());

        /** @var StaticEntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = new StaticEntityRepository([], new DocumentFileDefinition());

        /** @var StaticEntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = new StaticEntityRepository([], new DocumentTypeDefinition());
        $documentTypeRepository->searches[] = [$documentTypeId];

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->with(static::isInstanceOf(Request::class))
            ->willReturn($mediaFile);
        $mediaService->expects($this->once())
            ->method('saveMediaFile')
            ->with(
                $mediaFile,
                'invoice',
                static::isInstanceOf(Context::class),
                DocumentPersister::MEDIA_FOLDER,
            )
            ->willReturn($mediaId);

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            $documentRepository,
            $documentFileRepository,
            $documentTypeRepository,
            $mediaService,
        );

        $response = $controller->upload(
            Request::create(
                '/api/_action/order/document-v2/upload?' . http_build_query([
                    'documentComment' => '',
                    'documentDate' => '2026-07-13T00:00:00.000Z',
                    'documentNumber' => '1000',
                    'documentType' => DocumentType::INVOICE->value,
                    'extension' => DocumentFormat::PDF->value,
                    'fileName' => 'invoice',
                    'fileType' => DocumentFormat::PDF->value,
                    'orderId' => $orderId,
                    'orderVersionId' => $orderVersionId,
                ], '', '&', \PHP_QUERY_RFC3986),
                Request::METHOD_POST,
                server: [
                    'CONTENT_LENGTH' => (string) \strlen($content),
                    'CONTENT_TYPE' => DocumentFormat::PDF->mimeType(),
                ],
                content: $content,
            ),
            Context::createDefaultContext(),
        );

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([DocumentFormat::PDF->value], $payload['fileTypes'] ?? null);
        static::assertSame($mediaId, $documentRepository->creates[0][0]['documentMediaFileId']);
        static::assertSame($mediaId, $documentFileRepository->creates[0][0]['mediaId']);
    }

    public function testDownloadReturnsStoredDocumentFile(): void
    {
        $documentId = Uuid::randomHex();
        $deepLinkCode = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName('invoice');
        $media->setFileExtension(DocumentFormat::PDF->value);
        $media->setMimeType(DocumentFormat::PDF->mimeType());

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId($documentId);
        $documentFile->setDocumentFormat(DocumentFormat::PDF->value);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDeepLinkCode($deepLinkCode);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ], new DocumentDefinition());

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn('pdf content');

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $documentRepository,
            new StaticEntityRepository([], new DocumentFileDefinition()),
            new StaticEntityRepository([], new DocumentTypeDefinition()),
            $mediaService,
        );

        $response = $controller->download(
            $documentId,
            $deepLinkCode,
            DocumentFormat::PDF->value,
            Context::createDefaultContext(),
        );

        static::assertSame('pdf content', $response->getContent());
        static::assertSame(DocumentFormat::PDF->mimeType(), $response->headers->get('content-type'));
        static::assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('invoice.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function testDownloadThrowsWhenRequestedFileTypeIsUnavailable(): void
    {
        $documentId = Uuid::randomHex();
        $deepLinkCode = Uuid::randomHex();

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDeepLinkCode($deepLinkCode);
        $document->setDocumentFiles(new DocumentFileCollection([]));

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([$document]),
        ], new DocumentDefinition());

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $documentRepository,
            new StaticEntityRepository([], new DocumentFileDefinition()),
            new StaticEntityRepository([], new DocumentTypeDefinition()),
            static::createStub(MediaService::class),
        );

        static::expectExceptionObject(
            DocumentV2Exception::documentFileTypeUnavailable($documentId, DocumentFormat::PDF->value)
        );

        $controller->download(
            $documentId,
            $deepLinkCode,
            DocumentFormat::PDF->value,
            Context::createDefaultContext(),
        );
    }

    public function testDownloadThrowsWhenDocumentIsNotFound(): void
    {
        $documentId = Uuid::randomHex();

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([]),
        ], new DocumentDefinition());

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $documentRepository,
            new StaticEntityRepository([], new DocumentFileDefinition()),
            new StaticEntityRepository([], new DocumentTypeDefinition()),
            static::createStub(MediaService::class),
        );

        static::expectExceptionObject(DocumentV2Exception::documentNotFound($documentId));

        $controller->download(
            $documentId,
            Uuid::randomHex(),
            DocumentFormat::PDF->value,
            Context::createDefaultContext(),
        );
    }

    private function createGenerator(
        DocumentRendererRegistry $rendererRegistry,
        string $orderId,
        ?DocumentEntity $document = null,
    ): DocumentGenerator {
        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([
            new OrderCollection([$order]),
            new OrderCollection([$order]),
        ], new OrderDefinition());

        $document ??= new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setDeepLinkCode(Uuid::randomHex());

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([
            [],
            new DocumentCollection([$document]),
        ], new DocumentDefinition());

        /** @var StaticEntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = new StaticEntityRepository([
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        /** @var StaticEntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = new StaticEntityRepository([
            [Uuid::randomHex()],
        ], new DocumentTypeDefinition());

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturn(Uuid::randomHex());

        return new DocumentGenerator(
            new DocumentDataProviderRegistry([
                new StaticDocumentDataProvider([DocumentType::INVOICE->value]),
            ]),
            $rendererRegistry,
            new DocumentNumberGenerator(static::createStub(NumberRangeValueGeneratorInterface::class)),
            new DocumentPersister(
                $documentRepository,
                $documentFileRepository,
                $documentTypeRepository,
                $mediaService,
            ),
            new DocumentDependencyResolver($rendererRegistry),
            $orderRepository,
        );
    }
}
