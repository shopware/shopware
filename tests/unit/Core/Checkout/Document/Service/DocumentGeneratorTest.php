<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\Service\AbstractDocumentTypeRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentGenerator::class)]
class DocumentGeneratorTest extends TestCase
{
    public function testReadDocumentFallsBackToV2FileWhenLegacyFileIsUnavailable(): void
    {
        $v2Media = new MediaEntity();
        $v2Media->setId(Uuid::randomHex());
        $v2Media->setFileName('invoice-v2');
        $v2Media->setFileExtension(PdfRenderer::FILE_EXTENSION);
        $v2Media->setMimeType(PdfRenderer::FILE_CONTENT_TYPE);

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentFormat(PdfRenderer::FILE_EXTENSION);
        $documentFile->setMedia($v2Media);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setStatic(true);
        $document->setOrderId(Uuid::randomHex());
        $document->setConfig([]);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($v2Media->getId(), static::isInstanceOf(Context::class))
            ->willReturn('v2 pdf content');

        $generator = new DocumentGenerator(
            new DocumentRendererRegistry([]),
            static::createStub(DocumentFileRendererRegistry::class),
            $mediaService,
            $documentRepository,
            static::createStub(Connection::class),
            new NativeClock(),
            $this->createDocumentFileResolver(),
        );

        $renderedDocument = $generator->readDocument($document->getId(), Context::createDefaultContext());

        static::assertNotNull($renderedDocument);
        static::assertSame('v2 pdf content', $renderedDocument->getContent());
        static::assertSame('invoice-v2.pdf', $renderedDocument->getName());
        static::assertSame(PdfRenderer::FILE_EXTENSION, $renderedDocument->getFileExtension());
        static::assertSame(PdfRenderer::FILE_CONTENT_TYPE, $renderedDocument->getContentType());
        static::assertStringNotContainsString('.pdf.pdf', $renderedDocument->getName());
    }

    public function testReadDocumentDoesNotRegenerateV2DocumentWhenRequestedFormatIsUnavailable(): void
    {
        $v2Media = new MediaEntity();
        $v2Media->setId(Uuid::randomHex());
        $v2Media->setFileName('invoice-v2');
        $v2Media->setFileExtension(HtmlRenderer::FILE_EXTENSION);
        $v2Media->setMimeType(HtmlRenderer::FILE_CONTENT_TYPE);

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentFormat(HtmlRenderer::FILE_EXTENSION);
        $documentFile->setMedia($v2Media);

        $documentType = new DocumentTypeEntity();
        $documentType->setTechnicalName(InvoiceRenderer::TYPE);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setStatic(false);
        $document->setOrderId(Uuid::randomHex());
        $document->setConfig([]);
        $document->setDocumentType($documentType);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');
        $mediaService->expects($this->never())->method('saveFile');

        $renderer = $this->createMock(AbstractDocumentRenderer::class);
        $renderer->expects($this->never())->method('supports');
        $renderer->expects($this->never())->method('render');

        $generator = new DocumentGenerator(
            new DocumentRendererRegistry([$renderer]),
            static::createStub(DocumentFileRendererRegistry::class),
            $mediaService,
            $documentRepository,
            static::createStub(Connection::class),
            new NativeClock(),
            $this->createDocumentFileResolver(),
        );

        static::assertNull($generator->readDocument($document->getId(), Context::createDefaultContext()));
    }

    #[DataProvider('readDataProvider')]
    public function testReadDocument(string $fileType, RenderedDocument $resultRenderer, \Closure $expectClosure): void
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileExtension(PdfRenderer::FILE_EXTENSION);
        $media->setMimeType(PdfRenderer::FILE_CONTENT_TYPE);

        $mediaA11y = new MediaEntity();
        $mediaA11y->setId(Uuid::randomHex());
        $mediaA11y->setFileExtension(HtmlRenderer::FILE_EXTENSION);
        $mediaA11y->setMimeType(HtmlRenderer::FILE_CONTENT_TYPE);

        $documentType = new DocumentTypeEntity();
        $documentType->setId(Uuid::randomHex());
        $documentType->setName('invoice');
        $documentType->setTechnicalName('invoice');

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setStatic(false);
        $document->setOrderId(Uuid::randomHex());
        $document->setConfig([]);
        $document->setDocumentType($documentType);
        $document->setDocumentMediaFileId($media->getId());
        $document->setDocumentMediaFile($media);
        $document->setDocumentA11yMediaFileId($mediaA11y->getId());
        $document->setDocumentA11yMediaFile($mediaA11y);

        $context = Context::createDefaultContext();

        $resultRenderer->setContent('html');

        $result = new RendererResult();
        $result->addSuccess('orderId', $resultRenderer);

        $mockRenderer = static::createStub(AbstractDocumentRenderer::class);
        $registry = new DocumentRendererRegistry([$mockRenderer]);

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturnOnConsecutiveCalls(
            $document->getDocumentMediaFileId(),
            $document->getDocumentA11yMediaFileId(),
        );

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                $context,
            ),
        ]);

        $generator = new DocumentGenerator(
            $registry,
            static::createStub(DocumentFileRendererRegistry::class),
            $mediaService,
            $documentRepository,
            static::createStub(Connection::class),
            new NativeClock(),
            $this->createDocumentFileResolver(),
        );

        try {
            $renderedDocument = $generator->readDocument($document->getId(), $context, 'deepLinkCode', $fileType);
        } catch (DocumentException $e) {
            $expectClosure($e);

            return;
        }

        static::assertNotNull($renderedDocument);
        $expectClosure($renderedDocument);
    }

    public function testReadDocumentAutoDetectsXmlFileTypeWhenNullIsPassed(): void
    {
        $xmlMedia = new MediaEntity();
        $xmlMedia->setId(Uuid::randomHex());
        $xmlMedia->setFileExtension('xml');
        $xmlMedia->setFileName('invoice');
        $xmlMedia->setMimeType('application/xml');

        $documentType = new DocumentTypeEntity();
        $documentType->setId(Uuid::randomHex());
        $documentType->setTechnicalName('invoice');

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setStatic(false);
        $document->setOrderId(Uuid::randomHex());
        $document->setConfig([]);
        $document->setDocumentType($documentType);
        $document->setDocumentMediaFileId($xmlMedia->getId());
        $document->setDocumentMediaFile($xmlMedia);

        $context = Context::createDefaultContext();

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                $context,
            ),
        ]);

        $generator = new DocumentGenerator(
            new DocumentRendererRegistry([]),
            static::createStub(DocumentFileRendererRegistry::class),
            static::createStub(MediaService::class),
            $documentRepository,
            static::createStub(Connection::class),
            new NativeClock(),
            $this->createDocumentFileResolver(),
        );

        $renderedDocument = $generator->readDocument($document->getId(), $context, '', null);

        static::assertNotNull($renderedDocument);
        static::assertSame('xml', $renderedDocument->getFileExtension());
        static::assertSame('application/xml', $renderedDocument->getContentType());
    }

    public function testPreview(): void
    {
        $operation = new DocumentGenerateOperation(
            'orderId',
            HtmlRenderer::FILE_EXTENSION,
        );

        $context = Context::createDefaultContext();

        $resultRenderer = new RenderedDocument(
            name: 'invoice',
            fileExtension: 'html',
            contentType: 'text/html',
        );
        $resultRenderer->setContent('html');

        $result = new RendererResult();
        $result->addSuccess('orderId', $resultRenderer);

        $mockRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $mockRenderer->method('supports')->willReturn('invoice');
        $mockRenderer
            ->expects($this->once())
            ->method('render')
            ->with(
                ['orderId' => $operation],
                $context,
                static::callback(static fn (DocumentRendererConfig $config): bool => $config->deepLinkCode === 'deepLinkCode')
            )
            ->willReturn($result);

        $mockTypeRenderer = static::createStub(AbstractDocumentTypeRenderer::class);
        $mockTypeRenderer->method('getContentType')->willReturn('text/html');
        $mockTypeRenderer->method('render')->willReturn('html');

        $registry = new DocumentRendererRegistry([$mockRenderer]);

        $documentRepository = new StaticEntityRepository([]);

        $fileRendererRegistry = static::createStub(DocumentFileRendererRegistry::class);

        $generator = new DocumentGenerator(
            $registry,
            $fileRendererRegistry,
            static::createStub(MediaService::class),
            $documentRepository,
            static::createStub(Connection::class),
            new NativeClock(),
            $this->createDocumentFileResolver(),
        );

        $document = $generator->preview('invoice', $operation, 'deepLinkCode', $context);

        static::assertSame($document->getContent(), 'html');
        static::assertSame($document->getFileExtension(), 'html');
        static::assertSame($document->getContentType(), 'text/html');
    }

    #[TestWith([InvoiceRenderer::TYPE])]
    #[TestWith([ZugferdRenderer::TYPE])]
    #[TestWith([ZugferdEmbeddedRenderer::TYPE])]
    public function testPreviewSetsReferencedDocumentIdFromInvoiceNumber(string $invoiceType): void
    {
        $orderId = Uuid::randomHex();

        $renderedDocument = new RenderedDocument(name: 'credit_note', fileExtension: 'html', contentType: 'text/html');
        $renderedDocument->setContent('html');
        $rendererResult = new RendererResult();
        $rendererResult->addSuccess($orderId, $renderedDocument);

        $mockRenderer = static::createStub(AbstractDocumentRenderer::class);
        $mockRenderer->method('supports')->willReturn('credit_note');
        $mockRenderer->method('render')->willReturn($rendererResult);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params) use ($invoiceType): string {
                static::assertContains($invoiceType, $params['technicalNames'] ?? []);

                return Uuid::randomHex();
            });

        $documentRepository = new StaticEntityRepository([]);

        $generator = new DocumentGenerator(
            new DocumentRendererRegistry([$mockRenderer]),
            static::createStub(DocumentFileRendererRegistry::class),
            static::createStub(MediaService::class),
            $documentRepository,
            $connection,
            new NativeClock(),
            $this->createDocumentFileResolver(),
        );

        $operation = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION, ['custom' => ['invoiceNumber' => 'INV-100']]);

        $generator->preview('credit_note', $operation, 'deepLinkCode', Context::createDefaultContext());
    }

    public function testPreviewErrorThrowsDocumentException(): void
    {
        $operation = new DocumentGenerateOperation(
            'orderId',
            FileTypes::PDF,
            [],
            null,
            false,
            true
        );
        $context = Context::createDefaultContext();

        $result = new RendererResult();
        $result->addError('orderId', new \Exception('Some Error Message.'));

        $mockRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $mockRenderer->method('supports')->willReturn('invoice');
        $mockRenderer
            ->expects($this->once())
            ->method('render')
            ->with(
                ['orderId' => $operation],
                $context,
                static::callback(static fn (DocumentRendererConfig $config): bool => $config->deepLinkCode === 'deepLinkCode')
            )
            ->willReturn($result);

        $registry = new DocumentRendererRegistry([$mockRenderer]);

        $documentRepository = new StaticEntityRepository([]);

        $generator = new DocumentGenerator(
            $registry,
            static::createStub(DocumentFileRendererRegistry::class),
            static::createStub(MediaService::class),
            $documentRepository,
            static::createStub(Connection::class),
            new NativeClock(),
            $this->createDocumentFileResolver(),
        );

        $this->expectExceptionObject(DocumentException::generationError('Some Error Message.'));

        $generator->preview('invoice', $operation, 'deepLinkCode', $context);
    }

    /**
     * @param list<string|null> $mediaIds
     * @param array<string, DocumentGenerateOperation> $operations
     */
    #[DataProvider('generateDataProvider')]
    public function testGenerate(string $orderId, ?string $documentTypeId, array $mediaIds, RenderedDocument $resultRenderer, array $operations, \Closure $expectsClosure): void
    {
        $context = Context::createDefaultContext();

        $result = new RendererResult();
        $result->addSuccess($orderId, $resultRenderer);

        $mockRenderer = static::createStub(AbstractDocumentRenderer::class);
        $mockRenderer->method('supports')->willReturn('invoice');
        $mockRenderer
            ->method('render')
            ->willReturn($result);

        $mockTypeRenderer = static::createStub(AbstractDocumentTypeRenderer::class);
        $mockTypeRenderer->method('getContentType')->willReturn('text/html');
        $mockTypeRenderer->method('render')->willReturn('html');

        $registry = new DocumentRendererRegistry([$mockRenderer]);

        $documentRepository = new StaticEntityRepository([]);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($documentTypeId);

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willReturnOnConsecutiveCalls(
            $mediaIds[0] ?? '',
            $mediaIds[1] ?? '',
        );

        $fileRendererRegistry = static::createStub(DocumentFileRendererRegistry::class);
        $fileRendererRegistry->method('render')->willReturn('content');

        $generator = new DocumentGenerator(
            $registry,
            $fileRendererRegistry,
            $mediaService,
            $documentRepository,
            $connection,
            new NativeClock(),
            $this->createDocumentFileResolver(),
        );

        try {
            $document = $generator->generate('invoice', $operations, $context);
        } catch (\Exception $e) {
            $expectsClosure($e);

            return;
        }

        $expectsClosure($document);
    }

    /**
     * @return iterable<array{0: string, 1: RenderedDocument, 2: \Closure}>
     */
    public static function readDataProvider(): iterable
    {
        yield 'test read document with html format' => [
            HtmlRenderer::FILE_EXTENSION,

            new RenderedDocument(
                name: 'invoice',
                fileExtension: HtmlRenderer::FILE_EXTENSION,
                contentType: HtmlRenderer::FILE_CONTENT_TYPE,
            ),

            static function (RenderedDocument|DocumentException $renderedDocument): void {
                static::assertInstanceOf(RenderedDocument::class, $renderedDocument);

                static::assertSame($renderedDocument->getFileExtension(), HtmlRenderer::FILE_EXTENSION);
                static::assertSame($renderedDocument->getContentType(), HtmlRenderer::FILE_CONTENT_TYPE);
            },
        ];

        yield 'test read document with pdf format' => [
            PdfRenderer::FILE_EXTENSION,

            new RenderedDocument(
                name: 'invoice',
                fileExtension: PdfRenderer::FILE_EXTENSION,
                contentType: PdfRenderer::FILE_CONTENT_TYPE,
            ),

            static function (RenderedDocument|DocumentException $renderedDocument): void {
                static::assertInstanceOf(RenderedDocument::class, $renderedDocument);

                static::assertSame($renderedDocument->getFileExtension(), PdfRenderer::FILE_EXTENSION);
                static::assertSame($renderedDocument->getContentType(), PdfRenderer::FILE_CONTENT_TYPE);
            },
        ];

        yield 'test read document with invalid format' => [
            'xml',

            new RenderedDocument(
                name: 'invoice',
                fileExtension: 'xml',
                contentType: 'application/xml',
            ),

            static function (RenderedDocument|DocumentException $renderedDocument): void {
                static::assertInstanceOf(DocumentException::class, $renderedDocument);

                static::assertSame($renderedDocument->getErrorCode(), DocumentException::DOCUMENT_INVALID_RENDERER_TYPE);
                static::assertSame($renderedDocument->getMessage(), 'Unable to find a document renderer with type "invoice"');
            },
        ];
    }

    /**
     * @return iterable<array{0: string, 1: string|null, 2: list<string|null>, 3: RenderedDocument, 4: array<string, DocumentGenerateOperation>, 5: \Closure}>
     */
    public static function generateDataProvider(): iterable
    {
        $orderId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();

        $mediaId = Uuid::randomHex();
        $mediaA11yId = Uuid::randomHex();

        yield 'testGeneratePdfAndHtml' => [
            $orderId,
            $documentTypeId,
            [$mediaId, $mediaA11yId],
            new RenderedDocument(
                name: 'invoice',
                content: 'test'
            ),
            [
                $orderId => new DocumentGenerateOperation(
                    $orderId,
                ),
            ],

            static function (DocumentGenerationResult|DocumentException $result) use ($mediaId, $mediaA11yId): void {
                static::assertInstanceOf(DocumentGenerationResult::class, $result);

                static::assertNotNull($struct = $result->getSuccess()->first());
                static::assertNotNull($struct->getId());
                static::assertNotNull($struct->getMediaId());
                static::assertSame($struct->getMediaId(), $mediaId);
                static::assertNotNull($struct->getA11yMediaId());
                static::assertSame($struct->getA11yMediaId(), $mediaA11yId);
            },
        ];

        yield 'testGenerateOnlyPdf' => [
            $orderId,
            $documentTypeId,
            [$mediaId, null],
            new RenderedDocument(
                name: 'invoice',
                content: 'test',
            ),
            [
                $orderId => new DocumentGenerateOperation(
                    $orderId,
                ),
            ],

            static function (DocumentGenerationResult|DocumentException $result) use ($mediaId): void {
                static::assertInstanceOf(DocumentGenerationResult::class, $result);

                static::assertNotNull($struct = $result->getSuccess()->first());
                static::assertNotNull($struct->getId());
                static::assertNotNull($struct->getMediaId());
                static::assertSame($struct->getMediaId(), $mediaId);
                static::assertEmpty($struct->getA11yMediaId());
            },
        ];

        yield 'testGenerateErrorThrowsInvalidDocumentRenderer' => [
            $orderId,
            null,
            [],
            new RenderedDocument(
                name: 'invoice',
            ),
            [
                $orderId => new DocumentGenerateOperation(
                    $orderId,
                ),
            ],

            static function (DocumentGenerationResult|DocumentException $result): void {
                static::assertInstanceOf(DocumentException::class, $result);
            },
        ];
    }

    private function createDocumentFileResolver(): DocumentFileResolver
    {
        return new DocumentFileResolver();
    }
}
