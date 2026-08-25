<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentReader;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentReader::class)]
class DocumentReaderTest extends TestCase
{
    public function testReadReturnsMatchingMediaContent(): void
    {
        $pdfMedia = $this->createMedia('pdf');
        $htmlMedia = $this->createMedia('html');

        $document = $this->createDocument([$pdfMedia, $htmlMedia]);

        $mediaService = static::createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($pdfMedia->getId())
            ->willReturn('pdf-content');

        $reader = new DocumentReader(
            $this->createDocumentRepository($document),
            $mediaService,
            new DocumentRendererRegistry([]),
            new DocumentFileResolver(),
        );

        $renderedDocument = $reader->read($document->getId(), Context::createDefaultContext(), '', 'pdf');

        static::assertSame($pdfMedia->getFileName() . '.pdf', $renderedDocument->getName());
        static::assertSame('pdf', $renderedDocument->getFileExtension());
        static::assertSame($pdfMedia->getMimeType(), $renderedDocument->getContentType());
        static::assertSame('pdf-content', $renderedDocument->getContent());
    }

    public function testReadReturnsFirstFileWhenFileTypeIsNull(): void
    {
        $pdfMedia = $this->createMedia('pdf');

        $document = $this->createDocument([$pdfMedia]);

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('loadFile')->willReturn('content');

        $reader = new DocumentReader(
            $this->createDocumentRepository($document),
            $mediaService,
            new DocumentRendererRegistry([]),
            new DocumentFileResolver(),
        );

        $renderedDocument = $reader->read($document->getId(), Context::createDefaultContext(), '', null);

        static::assertSame('pdf', $renderedDocument->getFileExtension());
    }

    public function testReadThrowsWhenNoMatchingFormatExists(): void
    {
        $document = $this->createDocument([$this->createMedia('pdf')]);

        $reader = new DocumentReader(
            $this->createDocumentRepository($document),
            static::createStub(MediaService::class),
            new DocumentRendererRegistry([]),
            new DocumentFileResolver(),
        );

        $this->expectExceptionObject(DocumentV2Exception::documentFormatUnavailable($document->getId(), 'xml'));

        $reader->read($document->getId(), Context::createDefaultContext(), '', 'xml');
    }

    public function testReadThrowsWhenDocumentHasNoFiles(): void
    {
        $document = $this->createDocument([]);

        $reader = new DocumentReader(
            $this->createDocumentRepository($document),
            static::createStub(MediaService::class),
            new DocumentRendererRegistry([]),
            new DocumentFileResolver(),
        );

        $this->expectExceptionObject(DocumentV2Exception::documentFormatUnavailable($document->getId(), 'pdf'));

        $reader->read($document->getId(), Context::createDefaultContext(), '', 'pdf');
    }

    public function testReadDisambiguatesFormatsSharingTheSameFileExtension(): void
    {
        // pdf and zugferd_embedded_pdf are both rendered as .pdf, so only the exact
        // DocumentFormat value - not the file extension - can tell them apart.
        $plainPdfMedia = $this->createMedia('pdf', 'invoice');
        $zugferdMedia = $this->createMedia('pdf', 'invoice_zugferd');

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig([]);
        $document->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile(DocumentFormat::PDF->value, $plainPdfMedia),
            $this->createDocumentFile(DocumentFormat::ZUGFERD_EMBEDDED_PDF->value, $zugferdMedia),
        ]));

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('loadFile')->willReturn('content');

        $reader = new DocumentReader($this->createDocumentRepository($document, calls: 2), $mediaService, new DocumentRendererRegistry([]), new DocumentFileResolver());

        $plainResult = $reader->read($document->getId(), Context::createDefaultContext(), '', DocumentFormat::PDF->value);
        $zugferdResult = $reader->read($document->getId(), Context::createDefaultContext(), '', DocumentFormat::ZUGFERD_EMBEDDED_PDF->value);

        static::assertSame('invoice.pdf', $plainResult->getName());
        static::assertSame('invoice_zugferd.pdf', $zugferdResult->getName());
    }

    public function testReadFallsBackToRendererRegistryFileExtensionWhenMediaHasNone(): void
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('invoice');
        $media->setMimeType('application/pdf');

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig([]);
        $document->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile(DocumentFormat::PDF->value, $media),
        ]));

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('loadFile')->willReturn('content');

        $reader = new DocumentReader(
            $this->createDocumentRepository($document),
            $mediaService,
            new DocumentRendererRegistry([
                new StaticDocumentRenderer(DocumentFormat::PDF, fileExtension: 'pdf'),
            ]),
            new DocumentFileResolver(),
        );

        $renderedDocument = $reader->read($document->getId(), Context::createDefaultContext(), '', DocumentFormat::PDF->value);

        static::assertSame('invoice.pdf', $renderedDocument->getName());
        static::assertSame('pdf', $renderedDocument->getFileExtension());
    }

    public function testReadThrowsWhenNeitherMediaNorRendererRegistryProvideAFileExtension(): void
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('invoice');
        $media->setMimeType('application/pdf');

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig([]);
        // A format outside DocumentFormat, so the resolver cannot derive the extension from the
        // enum either and the renderer registry stays the only possible source.
        $document->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile('custom_format', $media),
        ]));

        $reader = new DocumentReader(
            $this->createDocumentRepository($document),
            static::createStub(MediaService::class),
            new DocumentRendererRegistry([]),
            new DocumentFileResolver(),
        );

        $this->expectExceptionObject(
            DocumentV2Exception::documentFileExtensionUnavailable($document->getId(), 'custom_format')
        );

        $reader->read($document->getId(), Context::createDefaultContext(), '', 'custom_format');
    }

    public function testReadThrowsWhenDocumentNotFound(): void
    {
        $documentRepository = StaticEntityRepository::of(DocumentCollection::class, [
            new DocumentCollection([]),
        ], new DocumentDefinition());

        $reader = new DocumentReader($documentRepository, static::createStub(MediaService::class), new DocumentRendererRegistry([]), new DocumentFileResolver());

        $this->expectExceptionObject(DocumentV2Exception::documentNotFound('unknown-id'));

        $reader->read('unknown-id', Context::createDefaultContext());
    }

    /**
     * @param list<MediaEntity> $mediaFiles
     */
    private function createDocument(array $mediaFiles): DocumentEntity
    {
        $documentFiles = array_map(
            fn (MediaEntity $media): DocumentFileEntity => $this->createDocumentFile($media->getFileExtension() ?? '', $media),
            $mediaFiles,
        );

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig([]);
        $document->setDocumentFiles(new DocumentFileCollection($documentFiles));

        return $document;
    }

    private function createDocumentFile(string $format, MediaEntity $media): DocumentFileEntity
    {
        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($media->getId());
        $documentFile->setMedia($media);

        return $documentFile;
    }

    private function createMedia(string $fileExtension, string $fileName = 'invoice'): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName($fileName);
        $media->setFileExtension($fileExtension);
        $media->setMimeType('application/' . $fileExtension);

        return $media;
    }

    /**
     * @return StaticEntityRepository<DocumentCollection>
     */
    private function createDocumentRepository(DocumentEntity $document, int $calls = 1): StaticEntityRepository
    {
        $search = static function (Criteria $criteria) use ($document): DocumentCollection {
            static::assertSame([$document->getId()], $criteria->getIds());

            return new DocumentCollection([$document]);
        };

        return StaticEntityRepository::of(DocumentCollection::class, array_fill(0, $calls, $search), new DocumentDefinition());
    }
}
