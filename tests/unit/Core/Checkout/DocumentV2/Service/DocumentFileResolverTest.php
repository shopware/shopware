<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\DocumentV2FileResolver;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\LegacyDocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentFileResolver::class)]
class DocumentFileResolverTest extends TestCase
{
    public function testPrefersV2FileOverLegacyFile(): void
    {
        $document = new DocumentEntity();
        $document->setId('document-id');
        $legacyFile = $this->resolvedFile(ResolvedDocumentFile::SOURCE_LEGACY);
        $v2File = $this->resolvedFile(ResolvedDocumentFile::SOURCE_V2);

        $legacyResolver = $this->createMock(LegacyDocumentFileResolver::class);
        $legacyResolver->expects($this->never())->method('resolve');

        $v2Resolver = $this->createMock(DocumentV2FileResolver::class);
        $v2Resolver->expects($this->once())
            ->method('resolve')
            ->with($document, 'pdf')
            ->willReturn($v2File);

        $resolvedFile = (new DocumentFileResolver($legacyResolver, $v2Resolver))->resolve($document, 'pdf');

        static::assertSame($v2File, $resolvedFile);
        static::assertNotSame($legacyFile, $resolvedFile);
    }

    public function testFallsBackToLegacyFileWhenV2FileIsUnavailable(): void
    {
        $document = new DocumentEntity();
        $document->setId('document-id');
        $legacyFile = $this->resolvedFile(ResolvedDocumentFile::SOURCE_LEGACY);

        $legacyResolver = $this->createMock(LegacyDocumentFileResolver::class);
        $legacyResolver->expects($this->once())
            ->method('resolve')
            ->with($document, 'pdf')
            ->willReturn($legacyFile);

        $v2Resolver = $this->createMock(DocumentV2FileResolver::class);
        $v2Resolver->expects($this->once())
            ->method('resolve')
            ->with($document, 'pdf')
            ->willReturn(null);

        $resolvedFile = (new DocumentFileResolver($legacyResolver, $v2Resolver))->resolve($document, 'pdf');

        static::assertSame($legacyFile, $resolvedFile);
    }

    public function testCanPreferLegacyFileOverV2File(): void
    {
        $document = new DocumentEntity();
        $document->setId('document-id');
        $legacyFile = $this->resolvedFile(ResolvedDocumentFile::SOURCE_LEGACY);

        $legacyResolver = $this->createMock(LegacyDocumentFileResolver::class);
        $legacyResolver->expects($this->once())
            ->method('resolve')
            ->with($document, 'pdf')
            ->willReturn($legacyFile);

        $v2Resolver = $this->createMock(DocumentV2FileResolver::class);
        $v2Resolver->expects($this->never())->method('resolve');

        $resolvedFile = (new DocumentFileResolver($legacyResolver, $v2Resolver))->resolve(
            $document,
            'pdf',
            ResolvedDocumentFile::SOURCE_LEGACY,
        );

        static::assertSame($legacyFile, $resolvedFile);
    }

    public function testNormalizesXmlFormatToZugferdXml(): void
    {
        $document = new DocumentEntity();
        $document->setId('document-id');
        $v2File = $this->resolvedFile(ResolvedDocumentFile::SOURCE_V2);

        $legacyResolver = $this->createMock(LegacyDocumentFileResolver::class);
        $legacyResolver->expects($this->never())->method('resolve');

        $v2Resolver = $this->createMock(DocumentV2FileResolver::class);
        $v2Resolver->expects($this->once())
            ->method('resolve')
            ->with($document, 'zugferd_xml')
            ->willReturn($v2File);

        $resolvedFile = (new DocumentFileResolver($legacyResolver, $v2Resolver))->resolve($document, 'xml');

        static::assertSame($v2File, $resolvedFile);
    }

    private function resolvedFile(string $source): ResolvedDocumentFile
    {
        $media = new MediaEntity();
        $media->setId($source . '-media-id');

        return new ResolvedDocumentFile(
            media: $media,
            format: 'pdf',
            fileExtension: 'pdf',
            mimeType: 'application/pdf',
            fileName: $source . '-document',
            source: $source,
        );
    }
}
