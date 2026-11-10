<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentCollection;
use Shopware\Core\Checkout\DocumentV2\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentArchiveGenerator;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileNameBuilder;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentMediaGuard;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The archives themselves are inspected in \Shopware\Tests\Integration\Core\Checkout\DocumentV2\Generation\DocumentArchiveGeneratorTest,
 * reading a zip needs it on disk.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentArchiveGenerator::class)]
class DocumentArchiveGeneratorTest extends TestCase
{
    private const DOWNLOAD_DATE = '2026-01-15';

    public function testArchiveReturnsNullWithoutDocumentFiles(): void
    {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setDocumentFiles(new DocumentFileCollection());

        $archive = $this->createArchiveGenerator(static::createStub(MediaService::class))
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNull($archive);
    }

    public function testArchiveThrowsWhenFileExtensionIsUnavailable(): void
    {
        $mediaId = Uuid::randomHex();
        $format = 'unknown_format';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName('invoice_1000_unknown');

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');

        static::expectExceptionObject(DocumentV2Exception::documentFileExtensionUnavailable($document->getId(), $format));

        $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());
    }

    public function testArchiveThrowsWhenMoreDocumentsThanTheLimitAreRequested(): void
    {
        $documents = new DocumentCollection();
        for ($i = 0; $i <= DocumentArchiveGenerator::MAX_DOCUMENTS; ++$i) {
            $document = new DocumentEntity();
            $document->setId(Uuid::randomHex());
            $document->setConfig(['documentNumber' => (string) $i]);
            $document->setDocumentFiles(new DocumentFileCollection());
            $documents->add($document);
        }

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');

        static::expectExceptionObject(DocumentV2Exception::documentArchiveLimitExceeded(
            DocumentArchiveGenerator::MAX_DOCUMENTS + 1,
            DocumentArchiveGenerator::MAX_DOCUMENTS,
        ));

        $this->createArchiveGenerator($mediaService)->archive($documents, Context::createDefaultContext());
    }

    private function createArchiveGenerator(MediaService $mediaService): DocumentArchiveGenerator
    {
        return new DocumentArchiveGenerator(
            $mediaService,
            new Filesystem(),
            new DocumentRendererRegistry([
                new StaticDocumentRenderer(DocumentFormat::PDF),
                new StaticDocumentRenderer(DocumentFormat::HTML),
                new StaticDocumentRenderer('custom_format', fileExtension: 'custom'),
            ]),
            new DocumentFileNameBuilder(new MockClock(self::DOWNLOAD_DATE . ' 10:00:00')),
            new DocumentMediaGuard(StaticEntityRepository::of(MediaFolderCollection::class, [[]])),
        );
    }
}
