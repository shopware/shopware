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
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentArchiveGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentFormatValidator;
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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentV2Controller::class)]
class DocumentV2ControllerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<DocumentCollection>
     */
    private StaticEntityRepository $documentRepository;

    /**
     * @var StaticEntityRepository<DocumentFileCollection>
     */
    private StaticEntityRepository $documentFileRepository;

    /**
     * @var StaticEntityRepository<DocumentTypeCollection>
     */
    private StaticEntityRepository $documentTypeRepository;

    protected function setUp(): void
    {
        $this->documentRepository = new StaticEntityRepository([], new DocumentDefinition());
        $this->documentFileRepository = new StaticEntityRepository([], new DocumentFileDefinition());
        $this->documentTypeRepository = new StaticEntityRepository([], new DocumentTypeDefinition());
    }

    public function testAvailableTypesReturnsFormatsForRendererSupportedDocumentTypes(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value, 'partial_cancellation']),
            new StaticDocumentRenderer(DocumentFormat::PDF, ['partial_cancellation']),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
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
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
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
                'formats' => [
                    DocumentFormat::HTML->value,
                ],
            ],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
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
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
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

        $this->documentTypeRepository->searches[] = [$documentTypeId];

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
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
                    'format' => DocumentFormat::PDF->value,
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
        static::assertSame([DocumentFormat::PDF->value], $payload['formats'] ?? null);

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
        ], $this->documentRepository->creates[0]);

        static::assertSame($payload['documentId'], $this->documentFileRepository->creates[0][0]['documentId']);
        static::assertSame(DocumentFormat::PDF->value, $this->documentFileRepository->creates[0][0]['documentFormat']);
        static::assertSame($mediaId, $this->documentFileRepository->creates[0][0]['mediaId']);
    }

    public function testUploadRejectsUnsupportedFormat(): void
    {
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
            static::createStub(MediaService::class),
        );

        static::expectExceptionObject(
            DocumentV2Exception::unsupportedDocumentFormat(
                DocumentFormat::PDF->value,
                DocumentType::INVOICE->value,
            )
        );

        $controller->upload(
            Request::create(
                '/api/_action/order/document-v2/upload',
                Request::METHOD_POST,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
                    'documentType' => DocumentType::INVOICE->value,
                    'format' => DocumentFormat::PDF->value,
                    'mediaId' => Uuid::randomHex(),
                    'orderId' => $orderId,
                    'orderVersionId' => $orderVersionId,
                ], \JSON_THROW_ON_ERROR),
            ),
            Context::createDefaultContext(),
        );
    }

    public function testUploadStoresBinaryRequestWithQueryPayload(): void
    {
        $documentTypeId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();
        $content = 'uploaded invoice';
        $mediaFile = new MediaFile('invoice.pdf', DocumentFormat::PDF->mimeType(), DocumentFormat::PDF->value, \strlen($content));

        $this->documentTypeRepository->searches[] = [$documentTypeId];

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
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
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
                    'format' => DocumentFormat::PDF->value,
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
        static::assertSame([DocumentFormat::PDF->value], $payload['formats'] ?? null);
        static::assertSame($mediaId, $this->documentRepository->creates[0][0]['documentMediaFileId']);
        static::assertSame($mediaId, $this->documentFileRepository->creates[0][0]['mediaId']);
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

        $this->documentRepository->searches[] = new DocumentCollection([$document]);

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
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
            $mediaService,
        );

        $response = $controller->download(
            $documentId,
            DocumentFormat::PDF->value,
            Context::createDefaultContext(),
        );

        static::assertSame('pdf content', $response->getContent());
        static::assertSame(DocumentFormat::PDF->mimeType(), $response->headers->get('content-type'));
        static::assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('invoice.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function testDownloadArchiveReturnsStoredDocumentFiles(): void
    {
        $documentId = Uuid::randomHex();
        $deepLinkCode = Uuid::randomHex();
        $pdfMediaId = Uuid::randomHex();
        $htmlMediaId = Uuid::randomHex();

        $pdfMedia = new MediaEntity();
        $pdfMedia->setId($pdfMediaId);
        $pdfMedia->setFileName('invoice');
        $pdfMedia->setFileExtension(DocumentFormat::PDF->value);
        $pdfMedia->setMimeType(DocumentFormat::PDF->mimeType());

        $pdfDocumentFile = new DocumentFileEntity();
        $pdfDocumentFile->setId(Uuid::randomHex());
        $pdfDocumentFile->setDocumentId($documentId);
        $pdfDocumentFile->setDocumentFormat(DocumentFormat::PDF->value);
        $pdfDocumentFile->setMediaId($pdfMediaId);
        $pdfDocumentFile->setMedia($pdfMedia);

        $htmlMedia = new MediaEntity();
        $htmlMedia->setId($htmlMediaId);
        $htmlMedia->setFileName('invoice');
        $htmlMedia->setFileExtension(DocumentFormat::HTML->value);
        $htmlMedia->setMimeType(DocumentFormat::HTML->mimeType());

        $htmlDocumentFile = new DocumentFileEntity();
        $htmlDocumentFile->setId(Uuid::randomHex());
        $htmlDocumentFile->setDocumentId($documentId);
        $htmlDocumentFile->setDocumentFormat(DocumentFormat::HTML->value);
        $htmlDocumentFile->setMediaId($htmlMediaId);
        $htmlDocumentFile->setMedia($htmlMedia);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDeepLinkCode($deepLinkCode);
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentFiles(new DocumentFileCollection([$pdfDocumentFile, $htmlDocumentFile]));

        $this->documentRepository->searches[] = new DocumentCollection([$document]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $pdfMediaId => 'pdf content',
                $htmlMediaId => 'html content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator($mediaService, new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
            $mediaService,
        );

        $response = $controller->downloadArchive(
            $documentId,
            Context::createDefaultContext(),
        );

        static::assertSame('application/zip', $response->headers->get('content-type'));
        static::assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('1000.zip', (string) $response->headers->get('content-disposition'));

        $tempFile = tempnam(sys_get_temp_dir(), 'document-v2-controller-test-');
        static::assertIsString($tempFile);
        $content = $response->getContent();
        static::assertIsString($content);
        static::assertNotFalse(file_put_contents($tempFile, $content));

        $zip = new \ZipArchive();
        static::assertTrue($zip->open($tempFile));
        static::assertSame('pdf content', $zip->getFromName('invoice-pdf.pdf'));
        static::assertSame('html content', $zip->getFromName('invoice-html.html'));
        $zip->close();

        (new Filesystem())->remove($tempFile);
    }

    public function testDownloadThrowsWhenRequestedFormatIsUnavailable(): void
    {
        $documentId = Uuid::randomHex();
        $deepLinkCode = Uuid::randomHex();

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDeepLinkCode($deepLinkCode);
        $document->setDocumentFiles(new DocumentFileCollection([]));

        $this->documentRepository->searches[] = new DocumentCollection([$document]);

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
            static::createStub(MediaService::class),
        );

        static::expectExceptionObject(
            DocumentV2Exception::documentFormatUnavailable($documentId, DocumentFormat::PDF->value)
        );

        $controller->download(
            $documentId,
            DocumentFormat::PDF->value,
            Context::createDefaultContext(),
        );
    }

    public function testDownloadThrowsWhenDocumentIsNotFound(): void
    {
        $documentId = Uuid::randomHex();

        $this->documentRepository->searches[] = new DocumentCollection([]);

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            new DocumentFormatValidator($rendererRegistry),
            new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()),
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
            static::createStub(MediaService::class),
        );

        static::expectExceptionObject(DocumentV2Exception::documentNotFound($documentId));

        $controller->download(
            $documentId,
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
