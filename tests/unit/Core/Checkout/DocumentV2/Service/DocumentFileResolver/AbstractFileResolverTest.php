<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service\DocumentFileResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\AbstractFileResolver;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\TestingFileResolver;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AbstractFileResolver::class)]
class AbstractFileResolverTest extends TestCase
{
    public function testUsesMediaMetadataForResolvedFile(): void
    {
        $media = new MediaEntity();
        $media->setId('media-id');
        $media->setFileName('invoice');
        $media->setFileExtension('pdf');
        $media->setMimeType('application/pdf');

        $document = new DocumentEntity();
        $document->setId('document-id');
        $document->setConfig(['documentNumber' => '1000']);

        $resolvedFile = (new TestingFileResolver($media))->resolve($document, 'pdf');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame($media, $resolvedFile->media);
        static::assertSame('pdf', $resolvedFile->format);
        static::assertSame('pdf', $resolvedFile->fileExtension);
        static::assertSame('application/pdf', $resolvedFile->mimeType);
        static::assertSame('invoice', $resolvedFile->fileName);
        static::assertSame(ResolvedDocumentFile::SOURCE_V2, $resolvedFile->source);
    }

    public function testFallsBackToDocumentNumberAndDocumentFormatMetadata(): void
    {
        $media = new MediaEntity();
        $media->setId('media-id');

        $document = new DocumentEntity();
        $document->setId('document-id');
        $document->setConfig(['documentNumber' => '1000']);

        $resolvedFile = (new TestingFileResolver($media))->resolve($document, 'pdf');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame('pdf', $resolvedFile->fileExtension);
        static::assertSame('application/pdf', $resolvedFile->mimeType);
        static::assertSame('1000', $resolvedFile->fileName);
    }

    public function testFallsBackToDocumentIdAndGenericMimeTypeForUnknownFormat(): void
    {
        $media = new MediaEntity();
        $media->setId('media-id');

        $document = new DocumentEntity();
        $document->setId('document-id');
        $document->setConfig([]);

        $resolvedFile = (new TestingFileResolver($media))->resolve($document, 'custom_format');

        static::assertInstanceOf(ResolvedDocumentFile::class, $resolvedFile);
        static::assertSame('', $resolvedFile->fileExtension);
        static::assertSame('application/octet-stream', $resolvedFile->mimeType);
        static::assertSame('document-id', $resolvedFile->fileName);
    }
}
