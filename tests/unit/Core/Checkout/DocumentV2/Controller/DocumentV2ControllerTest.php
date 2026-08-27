<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\App\AppDocumentTypeConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\Controller\DocumentV2Controller;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentArchiveGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Generation\ReferencedDocumentResolver;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Doctrine\FakeQueryBuilder;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentDataProvider;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

    private AppFeatureStorage $appFeatureStorage;

    protected function setUp(): void
    {
        $this->documentRepository = new StaticEntityRepository([], new DocumentDefinition());
        $this->documentFileRepository = new StaticEntityRepository([], new DocumentFileDefinition());
        $this->documentTypeRepository = new StaticEntityRepository([], new DocumentTypeDefinition());

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([]);
        $this->appFeatureStorage = $storage;
    }

    public function testAvailableTypesReturnsFormatsFromTypeRegistry(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML),
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $typeRegistry = new DocumentTypeRegistry([
            new StaticDocumentType(DocumentType::INVOICE->value, [DocumentFormat::HTML->value]),
            new StaticDocumentType('partial_cancellation', [DocumentFormat::HTML->value, DocumentFormat::PDF->value]),
        ], $this->appFeatureStorage);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $typeRegistry,
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
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

    public function testAvailableTypesIncludesAppDeclaredLabels(): void
    {
        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistryWithAppType('swag_warranty', [DocumentFormat::PDF->value]),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->availableTypes();
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame([DocumentFormat::PDF->value], $payload['documentTypes']['swag_warranty']['formats']);
        static::assertSame(['en-GB' => 'Warranty'], $payload['documentTypes']['swag_warranty']['label']);
    }

    public function testCreateReturnsGeneratedDocumentResponse(): void
    {
        $orderId = Uuid::randomHex();

        $document = new DocumentEntity();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId, $document),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->create(
            new DocumentGenerationRequest(
                $orderId,
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
            new StaticDocumentRenderer(DocumentFormat::HTML),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->preview(
            new DocumentGenerationRequest(
                $orderId,
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
        $this->documentRepository->searches[] = $this->createUploadedDocumentSearch();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->upload(
            Request::create(
                '/api/_action/order/document-v2/upload',
                Request::METHOD_POST,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
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
                    'documentNumber' => '1000',
                ],
            ],
        ], $this->documentRepository->creates[0]);

        static::assertSame($payload['documentId'], $this->documentFileRepository->creates[0][0]['documentId']);
        static::assertSame(DocumentFormat::PDF->value, $this->documentFileRepository->creates[0][0]['documentFormat']);
        static::assertSame($mediaId, $this->documentFileRepository->creates[0][0]['mediaId']);
    }

    public function testUploadResolvesTheAppProvidedSentinelForAnAppDocumentType(): void
    {
        $sentinelId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $this->documentTypeRepository->searches[] = [];
        $this->documentTypeRepository->searches[] = [$sentinelId];
        $this->documentRepository->searches[] = $this->createUploadedDocumentSearch();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            $this->createTypeRegistryWithAppType('swag_warranty', [DocumentFormat::PDF->value]),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(
                null,
                $this->createTypeRegistryWithAppType('swag_warranty', [DocumentFormat::PDF->value]),
            ),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->upload(
            Request::create(
                '/api/_action/order/document-v2/upload',
                Request::METHOD_POST,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
                    'documentNumber' => '1000',
                    'documentType' => 'swag_warranty',
                    'format' => DocumentFormat::PDF->value,
                    'mediaId' => $mediaId,
                    'orderId' => $orderId,
                    'orderVersionId' => $orderVersionId,
                ], \JSON_THROW_ON_ERROR),
            ),
            Context::createDefaultContext(),
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertSame($sentinelId, $this->documentRepository->creates[0][0]['documentTypeId']);
    }

    public function testUploadRejectsUnsupportedFormat(): void
    {
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            $this->createTypeRegistry([DocumentFormat::HTML->value]),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
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
        $this->documentRepository->searches[] = $this->createUploadedDocumentSearch();

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->with(static::isInstanceOf(Request::class))
            ->willReturn($mediaFile);

        $fileNameProvider = $this->createMock(FileNameProvider::class);
        $fileNameProvider->expects($this->once())
            ->method('provide')
            ->with('invoice', DocumentFormat::PDF->value, null, static::isInstanceOf(Context::class))
            ->willReturn('invoice_(1)');

        $mediaService->expects($this->once())
            ->method('saveMediaFile')
            ->with(
                $mediaFile,
                'invoice_(1)',
                static::isInstanceOf(Context::class),
                DocumentPersister::MEDIA_FOLDER,
            )
            ->willReturn($mediaId);

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, $orderId),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            $mediaService,
            $fileNameProvider,
            $this->createDocumentFileResolver(),
        );

        $response = $controller->upload(
            Request::create(
                '/api/_action/order/document-v2/upload?' . http_build_query([
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
        $media->setMimeType(DocumentFormat::PDF->mimeType());

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId($documentId);
        $documentFile->setDocumentFormat(DocumentFormat::PDF->value);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setConfig([]);
        $document->setDeepLinkCode($deepLinkCode);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        $this->documentRepository->searches[] = new DocumentCollection([$document]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn('pdf content');

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            $mediaService,
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
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

    public function testDownloadFallsBackToLegacyDocumentFile(): void
    {
        $documentId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName('legacy-invoice');
        $media->setFileExtension(DocumentFormat::PDF->fileExtension());
        $media->setMimeType(DocumentFormat::PDF->mimeType());

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setConfig([]);
        $document->setDocumentMediaFile($media);
        $document->setDocumentFiles(new DocumentFileCollection([]));

        $this->documentRepository->searches[] = new DocumentCollection([$document]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn('legacy pdf content');

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            $mediaService,
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->download($documentId, DocumentFormat::PDF->value, Context::createDefaultContext());

        static::assertSame('legacy pdf content', $response->getContent());
        static::assertSame(DocumentFormat::PDF->mimeType(), $response->headers->get('content-type'));
        static::assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('legacy-invoice.pdf', (string) $response->headers->get('content-disposition'));
        static::assertStringNotContainsString('legacy-invoice.pdf.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function testDownloadFallsBackToLegacyDocumentFileWithCustomExtension(): void
    {
        $documentId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName('legacy-document');
        $media->setFileExtension('custom');
        $media->setMimeType('application/custom');

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setConfig([]);
        $document->setDocumentMediaFile($media);
        $document->setDocumentFiles(new DocumentFileCollection([]));

        $this->documentRepository->searches[] = new DocumentCollection([$document]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn('legacy custom content');

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            $mediaService,
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->download($documentId, 'custom', Context::createDefaultContext());

        static::assertSame('legacy custom content', $response->getContent());
        static::assertSame('application/custom', $response->headers->get('content-type'));
        static::assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('legacy-document.custom', (string) $response->headers->get('content-disposition'));
    }

    public function testDownloadResolvesV2DocumentFileExtensionFromRenderer(): void
    {
        $documentId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $format = 'custom_format';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName('v2-custom-document');
        $media->setMimeType('application/custom');

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId($documentId);
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setConfig([]);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        $this->documentRepository->searches[] = new DocumentCollection([$document]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn('v2 custom content');

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer($format, fileExtension: 'custom'),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            $mediaService,
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->download($documentId, $format, Context::createDefaultContext());

        static::assertSame('v2 custom content', $response->getContent());
        static::assertSame('application/custom', $response->headers->get('content-type'));
        static::assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('v2-custom-document.custom', (string) $response->headers->get('content-disposition'));
    }

    public function testDownloadArchiveReturnsStoredDocumentFiles(): void
    {
        $documentId = Uuid::randomHex();
        $deepLinkCode = Uuid::randomHex();
        $pdfMediaId = Uuid::randomHex();
        $htmlMediaId = Uuid::randomHex();

        $pdfMedia = new MediaEntity();
        $pdfMedia->setId($pdfMediaId);
        $pdfMedia->setFileName('invoice_1000_pdf');
        $pdfMedia->setFileExtension(DocumentFormat::PDF->fileExtension());
        $pdfMedia->setMimeType(DocumentFormat::PDF->mimeType());

        $pdfDocumentFile = new DocumentFileEntity();
        $pdfDocumentFile->setId(Uuid::randomHex());
        $pdfDocumentFile->setDocumentId($documentId);
        $pdfDocumentFile->setDocumentFormat(DocumentFormat::PDF->value);
        $pdfDocumentFile->setMediaId($pdfMediaId);
        $pdfDocumentFile->setMedia($pdfMedia);

        $htmlMedia = new MediaEntity();
        $htmlMedia->setId($htmlMediaId);
        $htmlMedia->setFileName('invoice_1000_html');
        $htmlMedia->setFileExtension(DocumentFormat::HTML->fileExtension());
        $htmlMedia->setMimeType(DocumentFormat::HTML->mimeType());

        $htmlDocumentFile = new DocumentFileEntity();
        $htmlDocumentFile->setId(Uuid::randomHex());
        $htmlDocumentFile->setDocumentId($documentId);
        $htmlDocumentFile->setDocumentFormat(DocumentFormat::HTML->value);
        $htmlDocumentFile->setMediaId($htmlMediaId);
        $htmlDocumentFile->setMedia($htmlMedia);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber('10000');

        $documentType = new DocumentTypeEntity();
        $documentType->setId(Uuid::randomHex());
        $documentType->setTechnicalName(DocumentType::INVOICE->value);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDeepLinkCode($deepLinkCode);
        $document->setOrderId($order->getId());
        $document->setOrder($order);
        $document->setDocumentType($documentType);
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
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator($mediaService),
            $this->documentRepository,
            $this->createDocumentPersister(),
            $mediaService,
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $response = $controller->downloadArchive(
            Request::create(
                '/api/_action/order/document-v2/download-archive',
                Request::METHOD_POST,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['documentIds' => [$documentId]], \JSON_THROW_ON_ERROR),
            ),
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
        static::assertSame('pdf content', $zip->getFromName('invoice_1000_pdf.pdf'));
        static::assertSame('html content', $zip->getFromName('invoice_1000_html.html'));
        $zip->close();

        (new Filesystem())->remove($tempFile);
    }

    public function testDownloadArchiveThrowsWhenMoreDocumentsThanTheLimitAreRequested(): void
    {
        $controller = new DocumentV2Controller(
            $this->createGenerator(new DocumentRendererRegistry([]), Uuid::randomHex()),
            new DocumentRendererRegistry([]),
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $documentIds = [];
        for ($i = 0; $i <= DocumentArchiveGenerator::MAX_DOCUMENTS; ++$i) {
            $documentIds[] = Uuid::randomHex();
        }

        static::expectExceptionObject(DocumentV2Exception::documentArchiveLimitExceeded(
            DocumentArchiveGenerator::MAX_DOCUMENTS + 1,
            DocumentArchiveGenerator::MAX_DOCUMENTS,
        ));

        $controller->downloadArchive(
            Request::create(
                '/api/_action/order/document-v2/download-archive',
                Request::METHOD_POST,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['documentIds' => $documentIds], \JSON_THROW_ON_ERROR),
            ),
            Context::createDefaultContext(),
        );
    }

    public function testDownloadArchiveThrowsWhenDocumentIdsAreMissing(): void
    {
        $controller = new DocumentV2Controller(
            $this->createGenerator(new DocumentRendererRegistry([]), Uuid::randomHex()),
            new DocumentRendererRegistry([]),
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        static::expectExceptionObject(DocumentV2Exception::invalidRequestParameter('documentIds'));

        $controller->downloadArchive(
            Request::create(
                '/api/_action/order/document-v2/download-archive',
                Request::METHOD_POST,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['documentIds' => []], \JSON_THROW_ON_ERROR),
            ),
            Context::createDefaultContext(),
        );
    }

    public function testDownloadArchiveThrowsWhenNoDocumentsAreFound(): void
    {
        $documentIds = [Uuid::randomHex(), Uuid::randomHex()];

        $this->documentRepository->searches[] = new DocumentCollection([]);

        $controller = new DocumentV2Controller(
            $this->createGenerator(new DocumentRendererRegistry([]), Uuid::randomHex()),
            new DocumentRendererRegistry([]),
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        static::expectExceptionObject(DocumentV2Exception::documentArchiveUnavailable($documentIds));

        $controller->downloadArchive(
            Request::create(
                '/api/_action/order/document-v2/download-archive',
                Request::METHOD_POST,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['documentIds' => $documentIds], \JSON_THROW_ON_ERROR),
            ),
            Context::createDefaultContext(),
        );
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
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
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

    public function testDownloadThrowsWhenFileExtensionIsUnavailable(): void
    {
        $documentId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $format = 'custom_format';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName('invoice_1000_custom');

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId($documentId);
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setConfig([]);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        $this->documentRepository->searches[] = new DocumentCollection([$document]);

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            $mediaService,
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        static::expectExceptionObject(DocumentV2Exception::documentFileExtensionUnavailable($documentId, $format));

        $controller->download(
            $documentId,
            $format,
            Context::createDefaultContext(),
        );
    }

    public function testDownloadThrowsWhenDocumentIsNotFound(): void
    {
        $documentId = Uuid::randomHex();

        $this->documentRepository->searches[] = new DocumentCollection([]);

        $rendererRegistry = new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::PDF),
        ]);

        $controller = new DocumentV2Controller(
            $this->createGenerator($rendererRegistry, Uuid::randomHex()),
            $rendererRegistry,
            $this->createTypeRegistry(),
            $this->createArchiveGenerator(static::createStub(MediaService::class)),
            $this->documentRepository,
            $this->createDocumentPersister(),
            static::createStub(MediaService::class),
            static::createStub(FileNameProvider::class),
            $this->createDocumentFileResolver(),
        );

        $this->expectExceptionObject(DocumentV2Exception::documentNotFound($documentId));

        $controller->download(
            $documentId,
            DocumentFormat::PDF->value,
            Context::createDefaultContext(),
        );
    }

    private function createDocumentPersister(
        ?MediaService $mediaService = null,
        ?DocumentTypeRegistry $documentTypeRegistry = null,
    ): DocumentPersister {
        return new DocumentPersister(
            $this->documentRepository,
            $this->documentFileRepository,
            $this->documentTypeRepository,
            $mediaService ?? static::createStub(MediaService::class),
            $documentTypeRegistry ?? $this->createEmptyDocumentTypeRegistry(),
            static::createStub(FileNameProvider::class),
            static::createStub(EventDispatcherInterface::class),
        );
    }

    private function createEmptyDocumentTypeRegistry(): DocumentTypeRegistry
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([]);

        return new DocumentTypeRegistry([], $storage);
    }

    /**
     * @return callable(Criteria, Context, StaticEntityRepository<DocumentCollection>): DocumentCollection
     */
    private function createUploadedDocumentSearch(): callable
    {
        return static function (
            Criteria $criteria,
            Context $context,
            StaticEntityRepository $repository,
        ): DocumentCollection {
            $document = new DocumentEntity();
            $document->setId($repository->creates[0][0]['id']);
            $document->setOrderId($repository->creates[0][0]['orderId']);
            $document->setOrderVersionId($repository->creates[0][0]['orderVersionId']);
            $document->setDeepLinkCode($repository->creates[0][0]['deepLinkCode']);

            return new DocumentCollection([$document]);
        };
    }

    /**
     * @param list<string> $formats
     */
    private function createTypeRegistry(array $formats = [DocumentFormat::HTML->value, DocumentFormat::PDF->value]): DocumentTypeRegistry
    {
        return new DocumentTypeRegistry([
            new StaticDocumentType(DocumentType::INVOICE->value, $formats),
        ], $this->appFeatureStorage);
    }

    /**
     * @param list<string> $formats
     */
    private function createTypeRegistryWithAppType(string $identifier, array $formats): DocumentTypeRegistry
    {
        $feature = new AppFeature(
            appId: 'app-id',
            appName: 'SwagWarranty',
            appActive: true,
            appVersion: '1.0.0',
            appHasSecret: false,
            createdAt: new \DateTimeImmutable(),
            config: new AppDocumentTypeConfig($identifier, $formats, ['en-GB' => 'Warranty'], []),
        );

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([$feature]);

        return new DocumentTypeRegistry([], $storage);
    }

    private function createArchiveGenerator(MediaService $mediaService): DocumentArchiveGenerator
    {
        return new DocumentArchiveGenerator(
            $mediaService,
            new Filesystem(),
            new DocumentRendererRegistry([
                new StaticDocumentRenderer(DocumentFormat::PDF),
                new StaticDocumentRenderer(DocumentFormat::HTML),
            ]),
        );
    }

    private function createDocumentFileResolver(): DocumentFileResolver
    {
        return new DocumentFileResolver();
    }

    private function createGenerator(
        DocumentRendererRegistry $rendererRegistry,
        string $orderId,
        ?DocumentEntity $document = null,
    ): DocumentGenerator {
        $orderVersionId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setVersionId($orderVersionId);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());

        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $orderRepository = static::createStub(EntityRepository::class);
        $orderRepository->method('search')->willReturn($orderSearchResult);
        $orderRepository->method('createVersion')->willReturn($orderVersionId);

        $document ??= new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrderId($orderId);
        $document->setOrderVersionId($orderVersionId);
        $document->setDeepLinkCode(Uuid::randomHex());

        $documentRepository = StaticEntityRepository::of(DocumentCollection::class, [
            [],
            new DocumentCollection([$document]),
        ], new DocumentDefinition());

        $documentFileRepository = StaticEntityRepository::of(DocumentFileCollection::class, [
            new DocumentFileCollection([]),
        ], new DocumentFileDefinition());

        $documentTypeRepository = StaticEntityRepository::of(DocumentTypeCollection::class, [
            [Uuid::randomHex()],
        ], new DocumentTypeDefinition());

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturn(Uuid::randomHex());

        $fileNameProvider = static::createStub(FileNameProvider::class);
        $fileNameProvider->method('provide')->willReturnArgument(0);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn(new FakeQueryBuilder($connection, []));

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
                $this->createTypeRegistry(),
                $fileNameProvider,
                static::createStub(EventDispatcherInterface::class),
            ),
            new DocumentDependencyResolver($rendererRegistry),
            new ReferencedDocumentResolver(new ReferenceInvoiceLoader($connection), $connection),
            $orderRepository,
            static::createStub(ScriptExecutor::class),
        );
    }
}
