<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ResolvedDocumentFile::class)]
class ResolvedDocumentFileTest extends TestCase
{
    public function testStoresResolvedDocumentFileMetadata(): void
    {
        $media = new MediaEntity();
        $media->setId('media-id');

        $resolvedFile = new ResolvedDocumentFile(
            media: $media,
            format: 'pdf',
            fileExtension: 'pdf',
            mimeType: 'application/pdf',
            fileName: 'invoice',
            source: ResolvedDocumentFile::SOURCE_V2,
        );

        static::assertSame($media, $resolvedFile->media);
        static::assertSame('pdf', $resolvedFile->format);
        static::assertSame('pdf', $resolvedFile->fileExtension);
        static::assertSame('application/pdf', $resolvedFile->mimeType);
        static::assertSame('invoice', $resolvedFile->fileName);
        static::assertSame(ResolvedDocumentFile::SOURCE_V2, $resolvedFile->source);
    }
}
