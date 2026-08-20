<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service\DocumentFileResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\LegacyDocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(LegacyDocumentFileResolver::class)]
class LegacyDocumentFileResolverTest extends TestCase
{
    public function testResolvesLegacyPdfFile(): void
    {
        $media = $this->media('invoice.pdf', 'application/pdf');
        $document = $this->document(legacyMedia: $media);

        $resolvedFile = (new LegacyDocumentFileResolver())->resolve($document, 'pdf');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($media, $resolvedFile->media);
        static::assertSame('pdf', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_LEGACY, $resolvedFile->source);
    }

    public function testResolvesLegacyHtmlA11yFile(): void
    {
        $media = $this->media('invoice.html', 'text/html');
        $document = $this->document(legacyA11yMedia: $media);

        $resolvedFile = (new LegacyDocumentFileResolver())->resolve($document, 'html');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($media, $resolvedFile->media);
        static::assertSame('html', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_LEGACY, $resolvedFile->source);
    }

    public function testResolvesLegacyCustomExtension(): void
    {
        $media = $this->media('document.custom', 'application/custom');
        $document = $this->document(legacyMedia: $media);

        $resolvedFile = (new LegacyDocumentFileResolver())->resolve($document, 'custom');

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

        $resolvedFile = (new LegacyDocumentFileResolver())->resolve($document, 'zugferd_embedded_pdf');

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

        static::assertNull((new LegacyDocumentFileResolver())->resolve($document, 'zugferd_embedded_pdf'));
    }

    public function testReturnsNullWhenRequestedFormatIsUnavailable(): void
    {
        $document = $this->document(legacyMedia: $this->media('invoice.pdf', 'application/pdf'));

        static::assertNull((new LegacyDocumentFileResolver())->resolve($document, 'html'));
    }

    private function document(
        ?MediaEntity $legacyMedia = null,
        ?MediaEntity $legacyA11yMedia = null,
        ?string $documentType = null,
    ): DocumentEntity {
        $document = new DocumentEntity();
        $document->setId('document-id');
        $document->setConfig([]);
        $document->setDocumentMediaFile($legacyMedia);
        $document->setDocumentA11yMediaFile($legacyA11yMedia);

        if ($documentType !== null) {
            $type = new DocumentTypeEntity();
            $type->setTechnicalName($documentType);
            $document->setDocumentType($type);
        }

        return $document;
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
