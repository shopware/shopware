<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentFileResolver::class)]
class DocumentFileResolverTest extends TestCase
{
    public function testPrefersV2FileOverLegacyFile(): void
    {
        $v2Media = $this->media('v2-invoice.pdf', 'application/pdf');
        $legacyMedia = $this->media('legacy-invoice.pdf', 'application/pdf');
        $document = $this->document(
            documentFiles: [$this->documentFile('pdf', $v2Media)],
            legacyMedia: $legacyMedia,
        );

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'pdf');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($v2Media, $resolvedFile->media);
        static::assertSame('pdf', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_V2, $resolvedFile->source);
    }

    public function testFallsBackToLegacyFileWhenV2FileIsUnavailable(): void
    {
        $legacyMedia = $this->media('legacy-invoice.pdf', 'application/pdf');
        $document = $this->document(
            documentFiles: [$this->documentFile('html', $this->media('v2-invoice.html', 'text/html'))],
            legacyMedia: $legacyMedia,
        );

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'pdf');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($legacyMedia, $resolvedFile->media);
        static::assertSame('pdf', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_LEGACY, $resolvedFile->source);
    }

    public function testCanPreferLegacyFileOverV2File(): void
    {
        $v2Media = $this->media('v2-invoice.pdf', 'application/pdf');
        $legacyMedia = $this->media('legacy-invoice.pdf', 'application/pdf');
        $document = $this->document(
            documentFiles: [$this->documentFile('pdf', $v2Media)],
            legacyMedia: $legacyMedia,
        );

        $resolvedFile = (new DocumentFileResolver())->resolve(
            $document,
            'pdf',
            ResolvedDocumentFile::SOURCE_LEGACY,
        );

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($legacyMedia, $resolvedFile->media);
        static::assertSame(ResolvedDocumentFile::SOURCE_LEGACY, $resolvedFile->source);
    }

    public function testNormalizesXmlFormatToZugferdXml(): void
    {
        $xmlMedia = $this->media('invoice.xml', 'application/xml');
        $document = $this->document(
            documentFiles: [$this->documentFile('zugferd_xml', $xmlMedia)],
        );

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'xml');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($xmlMedia, $resolvedFile->media);
        static::assertSame('zugferd_xml', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_V2, $resolvedFile->source);
    }

    public function testResolvesLegacyHtmlA11yFile(): void
    {
        $media = $this->media('invoice.html', 'text/html');
        $document = $this->document(legacyA11yMedia: $media);

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'html');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($media, $resolvedFile->media);
        static::assertSame('html', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_LEGACY, $resolvedFile->source);
    }

    public function testResolvesLegacyCustomExtension(): void
    {
        $media = $this->media('document.custom', 'application/custom');
        $document = $this->document(legacyMedia: $media);

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'custom');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($media, $resolvedFile->media);
        static::assertSame('custom', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_LEGACY, $resolvedFile->source);
    }

    public function testResolvesEmbeddedPdfOnlyForEmbeddedLegacyDocumentType(): void
    {
        $media = $this->media('invoice.pdf', 'application/pdf');
        $document = $this->document(
            legacyMedia: $media,
            documentType: 'zugferd_embedded_invoice',
        );

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'zugferd_embedded_pdf');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($media, $resolvedFile->media);
        static::assertSame('zugferd_embedded_pdf', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_LEGACY, $resolvedFile->source);
    }

    public function testDoesNotTreatRegularLegacyPdfAsEmbeddedPdf(): void
    {
        $document = $this->document(
            legacyMedia: $this->media('invoice.pdf', 'application/pdf'),
            documentType: 'invoice',
        );

        static::assertNull((new DocumentFileResolver())->resolve($document, 'zugferd_embedded_pdf'));
    }

    public function testReturnsNullWhenRequestedFormatIsUnavailable(): void
    {
        $document = $this->document(
            documentFiles: [$this->documentFile('html', $this->media('invoice.html', 'text/html'))],
            legacyMedia: $this->media('invoice.pdf', 'application/pdf'),
        );

        static::assertNull((new DocumentFileResolver())->resolve($document, 'zugferd_xml'));
    }

    public function testReturnsNullWhenDocumentHasNoFiles(): void
    {
        $document = $this->document();

        static::assertNull((new DocumentFileResolver())->resolve($document, 'pdf'));
    }

    public function testUsesMediaMetadataForResolvedFile(): void
    {
        $media = $this->media('invoice.pdf', 'application/pdf');
        $document = $this->document(
            documentFiles: [$this->documentFile('pdf', $media)],
            documentNumber: '1000',
        );

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'pdf');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame('pdf', $resolvedFile->fileExtension);
        static::assertSame('application/pdf', $resolvedFile->mimeType);
        static::assertSame('invoice', $resolvedFile->fileName);
    }

    public function testFallsBackToDocumentNumberAndDocumentFormatMetadata(): void
    {
        $media = new MediaEntity();
        $media->setId('media-id');
        $document = $this->document(
            documentFiles: [$this->documentFile('pdf', $media)],
            documentNumber: '1000',
        );

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'pdf');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame('pdf', $resolvedFile->fileExtension);
        static::assertSame('application/pdf', $resolvedFile->mimeType);
        static::assertSame('1000', $resolvedFile->fileName);
    }

    public function testFallsBackToDocumentIdAndGenericMimeTypeForUnknownFormat(): void
    {
        $media = new MediaEntity();
        $media->setId('media-id');
        $document = $this->document(
            documentFiles: [$this->documentFile('custom_format', $media)],
        );

        $resolvedFile = (new DocumentFileResolver())->resolve($document, 'custom_format');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame('', $resolvedFile->fileExtension);
        static::assertSame('application/octet-stream', $resolvedFile->mimeType);
        static::assertSame('document-id', $resolvedFile->fileName);
    }

    /**
     * @param list<DocumentFileEntity> $documentFiles
     */
    private function document(
        array $documentFiles = [],
        ?MediaEntity $legacyMedia = null,
        ?MediaEntity $legacyA11yMedia = null,
        ?string $documentType = null,
        ?string $documentNumber = null,
    ): DocumentEntity {
        $document = new DocumentEntity();
        $document->setId('document-id');
        $document->setConfig($documentNumber !== null ? ['documentNumber' => $documentNumber] : []);
        $document->setDocumentFiles(new DocumentFileCollection($documentFiles));
        $document->setDocumentMediaFile($legacyMedia);
        $document->setDocumentA11yMediaFile($legacyA11yMedia);

        if ($documentType !== null) {
            $type = new DocumentTypeEntity();
            $type->setTechnicalName($documentType);
            $document->setDocumentType($type);
        }

        return $document;
    }

    private function documentFile(string $format, MediaEntity $media): DocumentFileEntity
    {
        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMedia($media);

        return $documentFile;
    }

    private function media(string $fileName, string $mimeType): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($fileName);
        $media->setFileName(pathinfo($fileName, \PATHINFO_FILENAME));
        $media->setFileExtension(pathinfo($fileName, \PATHINFO_EXTENSION));
        $media->setMimeType($mimeType);

        return $media;
    }
}
