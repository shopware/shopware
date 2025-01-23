<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Service\AbstractDocumentTypeRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(DocumentGenerator::class)]
#[Package('after-sales')]
class DocumentGeneratorTest extends TestCase
{
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

        $mockRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $registry = new DocumentRendererRegistry([$mockRenderer]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->method('saveFile')->willReturnOnConsecutiveCalls(
            $document->getDocumentMediaFileId(),
            $document->getDocumentA11yMediaFileId(),
        );

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
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
            $this->createMock(DocumentFileRendererRegistry::class),
            $mediaService,
            $documentRepository,
            $this->createMock(Connection::class),
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
            ->expects(static::once())
            ->method('render')
            ->with(
                ['orderId' => $operation],
                $context,
                static::callback(fn (DocumentRendererConfig $config): bool => $config->deepLinkCode === 'deepLinkCode')
            )
            ->willReturn($result);

        $mockTypeRenderer = $this->createMock(AbstractDocumentTypeRenderer::class);
        $mockTypeRenderer->method('getContentType')->willReturn('text/html');
        $mockTypeRenderer->method('render')->willReturn('html');

        $registry = new DocumentRendererRegistry([$mockRenderer]);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([]);

        $generator = new DocumentGenerator(
            $registry,
            $this->createMock(DocumentFileRendererRegistry::class),
            $this->createMock(MediaService::class),
            $documentRepository,
            $this->createMock(Connection::class),
        );

        $document = $generator->preview('invoice', $operation, 'deepLinkCode', $context);

        static::assertSame($document->getContent(), 'html');
        static::assertSame($document->getFileExtension(), 'html');
        static::assertSame($document->getContentType(), 'text/html');
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
            ->expects(static::once())
            ->method('render')
            ->with(
                ['orderId' => $operation],
                $context,
                static::callback(fn (DocumentRendererConfig $config): bool => $config->deepLinkCode === 'deepLinkCode')
            )
            ->willReturn($result);

        $registry = new DocumentRendererRegistry([$mockRenderer]);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([]);

        $generator = new DocumentGenerator(
            $registry,
            $this->createMock(DocumentFileRendererRegistry::class),
            $this->createMock(MediaService::class),
            $documentRepository,
            $this->createMock(Connection::class),
        );

        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('Unable to generate document. Some Error Message.');

        $generator->preview('invoice', $operation, 'deepLinkCode', $context);
    }

    /**
     * @param array<string, string|null> $mediaIds
     * @param array<string, DocumentGenerateOperation> $operations
     */
    #[DataProvider('generateDataProvider')]
    public function testGenerate(string $orderId, ?string $documentTypeId, array $mediaIds, RenderedDocument $resultRenderer, array $operations, \Closure $expectsClosure): void
    {
        $context = Context::createDefaultContext();

        $result = new RendererResult();
        $result->addSuccess($orderId, $resultRenderer);

        $mockRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $mockRenderer->method('supports')->willReturn('invoice');
        $mockRenderer
            ->method('render')
            ->willReturn($result);

        $mockTypeRenderer = $this->createMock(AbstractDocumentTypeRenderer::class);
        $mockTypeRenderer->method('getContentType')->willReturn('text/html');
        $mockTypeRenderer->method('render')->willReturn('html');

        $registry = new DocumentRendererRegistry([$mockRenderer]);

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
        $documentRepository = new StaticEntityRepository([]);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($documentTypeId);

        $fileRendererRegistry = $this->createMock(DocumentFileRendererRegistry::class);
        $fileRendererRegistry->method('render')->willReturn('html');

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->method('saveFile')->willReturnOnConsecutiveCalls(
            $mediaIds[0] ?? '',
            $mediaIds[1] ?? '',
        );

        $generator = new DocumentGenerator(
            $registry,
            $fileRendererRegistry,
            $mediaService,
            $documentRepository,
            $connection,
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

            function (RenderedDocument|DocumentException $renderedDocument): void {
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

            function (RenderedDocument|DocumentException $renderedDocument): void {
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

            function (RenderedDocument|DocumentException $renderedDocument): void {
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
            ),
            [
                $orderId => new DocumentGenerateOperation(
                    $orderId,
                ),
            ],

            function (DocumentGenerationResult|DocumentException $result) use ($mediaId, $mediaA11yId): void {
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
            ),
            [
                $orderId => new DocumentGenerateOperation(
                    $orderId,
                ),
            ],

            function (DocumentGenerationResult|DocumentException $result) use ($mediaId): void {
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

            function (DocumentGenerationResult|DocumentException $result): void {
                static::assertInstanceOf(DocumentException::class, $result);
            },
        ];
    }
}
