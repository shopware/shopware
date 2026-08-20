<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service\DocumentFileResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\DocumentV2FileResolver;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentV2FileResolver::class)]
class DocumentV2FileResolverTest extends TestCase
{
    public function testResolvesMatchingDocumentFile(): void
    {
        $pdfMedia = $this->media('invoice.pdf', 'application/pdf');
        $xmlMedia = $this->media('invoice.xml', 'application/xml');
        $document = $this->documentWithFiles([
            $this->documentFile('pdf', $pdfMedia),
            $this->documentFile('zugferd_xml', $xmlMedia),
        ]);

        $resolvedFile = (new DocumentV2FileResolver())->resolve($document, 'zugferd_xml');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($xmlMedia, $resolvedFile->media);
        static::assertSame('zugferd_xml', $resolvedFile->format);
        static::assertSame(ResolvedDocumentFile::SOURCE_V2, $resolvedFile->source);
    }

    public function testReturnsNullWhenRequestedFormatIsUnavailable(): void
    {
        $document = $this->documentWithFiles([
            $this->documentFile('html', $this->media('invoice.html', 'text/html')),
        ]);

        static::assertNull((new DocumentV2FileResolver())->resolve($document, 'pdf'));
    }

    public function testReturnsNullWhenDocumentHasNoV2Files(): void
    {
        $document = $this->documentWithFiles([]);

        static::assertNull((new DocumentV2FileResolver())->resolve($document, 'pdf'));
    }

    /**
     * @param list<DocumentFileEntity> $documentFiles
     */
    private function documentWithFiles(array $documentFiles): DocumentEntity
    {
        $document = new DocumentEntity();
        $document->setId('document-id');
        $document->setConfig([]);
        $document->setDocumentFiles(new DocumentFileCollection($documentFiles));

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
