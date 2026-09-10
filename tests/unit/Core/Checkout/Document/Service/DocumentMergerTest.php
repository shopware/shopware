<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\FpdiException;
use setasign\Fpdi\Tfpdf\Fpdi;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentMerger::class)]
class DocumentMergerTest extends TestCase
{
    public const PDF_CONTENT = 'PDF content for testing';

    private const DOWNLOAD_DATE = '2026-01-15';

    public function testMergeOneDocument(): void
    {
        $document = $this->createDocument(true);

        $fpdi = $this->createMock(Fpdi::class);
        $fpdi->expects($this->never())->method('setSourceFile');

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->expects($this->never())->method('generate');

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('loadFile')->willReturn(self::PDF_CONTENT);

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'document',
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )
        );

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            $documentGenerator,
            static::createStub(Fpdi::class),
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$document->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('pdf', $result->getFileExtension());
        static::assertSame('application/pdf', $result->getContentType());
        static::assertSame(self::PDF_CONTENT, $result->getContent());
        static::assertSame('document.pdf', $result->getName());
    }

    public function testMergeOneXmlDocumentPreservesOriginalMetadata(): void
    {
        $document = $this->createDocument(
            true,
            true,
            'xml',
            'application/xml',
            'invoice'
        );

        $fpdi = $this->createMock(Fpdi::class);
        $fpdi->expects($this->never())->method('setSourceFile');

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->expects($this->never())->method('generate');

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())->method('loadFile')->willReturn('<xml />');

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'document',
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )
        );

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            $documentGenerator,
            $fpdi,
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$document->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('xml', $result->getFileExtension());
        static::assertSame('application/xml', $result->getContentType());
        static::assertSame('<xml />', $result->getContent());
        static::assertSame('invoice.xml', $result->getName());
    }

    public function testMergeMultipleDocumentsUsingFpdi(): void
    {
        $firstDocument = $this->createDocument(true);
        $secondDocument = $this->createDocument(true);

        $fpdi = $this->createMock(Fpdi::class);
        $fpdi->expects($this->exactly(2))
            ->method('setSourceFile')
            ->willReturnOnConsecutiveCalls(1, 2);

        $fpdi->method('Output')->willReturn(self::PDF_CONTENT);

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                2,
                new DocumentCollection([$firstDocument, $secondDocument]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $documentGenerator = static::createStub(DocumentGenerator::class);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFileStream')
            ->willReturnCallback(static function () {
                return Utils::streamFor();
            });

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            $documentGenerator,
            $fpdi,
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$firstDocument->getId(), $secondDocument->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('pdf', $result->getFileExtension());
        static::assertSame(self::PDF_CONTENT, $result->getContent());
        static::assertSame('invoice_' . self::DOWNLOAD_DATE . '.pdf', $result->getName());
    }

    public function testMergeTriggersDocumentGenerationWhenMediaMissing(): void
    {
        $document = $this->createDocument(false);

        $documentWithMedia = clone $document;
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId(Uuid::randomHex());
        $mediaEntity->setFileExtension('pdf');
        $mediaEntity->setMimeType('application/pdf');
        $mediaEntity->setFileName('generated');
        $documentWithMedia->setDocumentMediaFileId($mediaEntity->getId());
        $documentWithMedia->setDocumentMediaFile($mediaEntity);

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    'document',
                    1,
                    new DocumentCollection([$document]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ),
                // The Second search is executed after a document was generated and returns the document WITH media
                new EntitySearchResult(
                    'document',
                    1,
                    new DocumentCollection([$documentWithMedia]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                )
            );

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->expects($this->exactly(1))
            ->method('generate')
            ->willReturnCallback(static function (string $documentType, array $operations) {
                $ids = array_keys($operations);
                $result = new DocumentGenerationResult();

                $result->addSuccess(new DocumentIdStruct($ids[0], '', Uuid::randomHex()));

                return $result;
            });

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())->method('loadFile')->willReturn(self::PDF_CONTENT);

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            $documentGenerator,
            static::createStub(Fpdi::class),
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$document->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('pdf', $result->getFileExtension());
        static::assertSame('application/pdf', $result->getContentType());
        static::assertSame('generated.pdf', $result->getName());
        static::assertSame(self::PDF_CONTENT, $result->getContent());
    }

    public function testMergeTriggersDocumentGenerationWithConfiguredFileTypeWhenMediaMissing(): void
    {
        $document = $this->createDocument(
            false,
            true,
            'pdf',
            'application/pdf',
            'document',
            ['fileTypes' => ['xml']]
        );

        $documentWithMedia = clone $document;
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId(Uuid::randomHex());
        $mediaEntity->setFileExtension('xml');
        $mediaEntity->setMimeType('application/xml');
        $mediaEntity->setFileName('generated-xml');
        $documentWithMedia->setDocumentMediaFileId($mediaEntity->getId());
        $documentWithMedia->setDocumentMediaFile($mediaEntity);

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    'document',
                    1,
                    new DocumentCollection([$document]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ),
                new EntitySearchResult(
                    'document',
                    1,
                    new DocumentCollection([$documentWithMedia]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                )
            );

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->expects($this->once())
            ->method('generate')
            ->willReturnCallback(static function (string $documentType, array $operations) {
                $operation = reset($operations);
                static::assertInstanceOf(DocumentGenerateOperation::class, $operation);
                static::assertSame('invoice', $documentType);
                static::assertSame('xml', $operation->getFileType());

                $result = new DocumentGenerationResult();
                $result->addSuccess(new DocumentIdStruct($operation->getDocumentId() ?? Uuid::randomHex(), '', Uuid::randomHex()));

                return $result;
            });

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())->method('loadFile')->willReturn('<xml />');

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            $documentGenerator,
            static::createStub(Fpdi::class),
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$document->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('xml', $result->getFileExtension());
        static::assertSame('application/xml', $result->getContentType());
        static::assertSame('generated-xml.xml', $result->getName());
        static::assertSame('<xml />', $result->getContent());
    }

    public function testMergeMultipleDocumentsSkipsDocumentsWithoutMediaAndDocumentType(): void
    {
        $fpdi = $this->createMock(Fpdi::class);
        $fpdi->expects($this->exactly(1))
            ->method('setSourceFile')
            ->willReturn(1);
        $fpdi->method('Output')
            ->willReturn(self::PDF_CONTENT);

        $firstDocument = $this->createDocument(true);
        $secondDocument = $this->createDocument(false, false);

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                2,
                new DocumentCollection([$firstDocument, $secondDocument]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFileStream')
            ->willReturnCallback(static function () {
                return Utils::streamFor();
            });

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            static::createStub(DocumentGenerator::class),
            $fpdi,
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$firstDocument->getId(), $secondDocument->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('pdf', $result->getFileExtension());
        static::assertSame(self::PDF_CONTENT, $result->getContent());
    }

    public function testMergeMultipleDocumentsWithFpdiFallbackToZipCreationWhenPdfMergeFails(): void
    {
        $firstDocument = $this->createDocument(true);
        $secondDocument = $this->createDocument(true);

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('search')->willReturn(
            new EntitySearchResult(
                'document',
                2,
                new DocumentCollection([$firstDocument, $secondDocument]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )
        );

        $fpdi = static::createStub(Fpdi::class);
        $fpdi->method('setSourceFile')->willThrowException(new FpdiException('PDF merge failed'));

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $filesystem->method('readFile')->willReturn('zip file content');
        $filesystem->expects($this->once())->method('remove');

        $documentMerger = new DocumentMerger(
            $documentRepository,
            static::createStub(MediaService::class),
            static::createStub(DocumentGenerator::class),
            $fpdi,
            $filesystem,
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$firstDocument->getId(), $secondDocument->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('zip', $result->getFileExtension());
        static::assertSame('application/zip', $result->getContentType());
        static::assertNotEmpty($result->getContent());
        static::assertSame('invoice_' . self::DOWNLOAD_DATE . '.zip', $result->getName());
    }

    public function testMergeMultipleDocumentsWithNonPdfFileTypesCreatesZip(): void
    {
        $firstDocument = $this->createDocument(true);
        $secondDocument = $this->createDocument(
            true,
            true,
            'xml',
            'application/xml',
            'invoice-xml'
        );

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('search')->willReturn(
            new EntitySearchResult(
                'document',
                2,
                new DocumentCollection([$firstDocument, $secondDocument]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )
        );

        $fpdi = $this->createMock(Fpdi::class);
        $fpdi->expects($this->never())->method('setSourceFile');

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnOnConsecutiveCalls('pdf content', '<xml />');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $filesystem->method('readFile')->willReturn('zip file content');
        $filesystem->expects($this->once())->method('remove');

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            static::createStub(DocumentGenerator::class),
            $fpdi,
            $filesystem,
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$firstDocument->getId(), $secondDocument->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('zip', $result->getFileExtension());
        static::assertSame('application/zip', $result->getContentType());
        static::assertSame('zip file content', $result->getContent());
        static::assertSame('invoice_' . self::DOWNLOAD_DATE . '.zip', $result->getName());
    }

    public function testCreateDocumentsZipThrowsExceptionWhenZipFileCannotBeRead(): void
    {
        $firstDocument = $this->createDocument(true);
        $secondDocument = $this->createDocument(true);

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('search')->willReturn(
            new EntitySearchResult(
                'document',
                2,
                new DocumentCollection([$firstDocument, $secondDocument]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )
        );

        $fpdi = static::createStub(Fpdi::class);
        $fpdi->method('setSourceFile')->willThrowException(new FpdiException('PDF merge failed'));

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('readFile')
            ->willThrowException(new IOException('Failed to read file'));
        $filesystem->expects($this->once())->method('exists')->willReturn(true);
        $filesystem->expects($this->once())->method('remove');

        $this->expectException(DocumentException::class);
        $this->expectExceptionMessageMatches('/^Cannot read document ZIP file:.*$/');

        $documentMerger = new DocumentMerger(
            $documentRepository,
            static::createStub(MediaService::class),
            static::createStub(DocumentGenerator::class),
            $fpdi,
            $filesystem,
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $documentMerger->merge(
            [$firstDocument->getId(), $secondDocument->getId()],
            Context::createDefaultContext()
        );
    }

    public function testDocumentGenerationFailsReturnsNull(): void
    {
        $document = $this->createDocument(false);

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('search')->willReturn(
            new EntitySearchResult(
                'document',
                2,
                new DocumentCollection([$document]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )
        );

        $documentGenerator = static::createStub(DocumentGenerator::class);
        $documentGenerator->method('generate')
            ->willReturn(new DocumentGenerationResult());

        $documentMerger = new DocumentMerger(
            $documentRepository,
            static::createStub(MediaService::class),
            $documentGenerator,
            static::createStub(Fpdi::class),
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$document->getId()],
            Context::createDefaultContext()
        );

        static::assertNull($result);
    }

    public function testPageSizesAreUsedFromSourcePdfs(): void
    {
        $document1 = $this->createDocument(true);
        $document2 = $this->createDocument(true);

        $document1->setConfig(
            [
                'pageOrientation' => 'portrait',
                'pageSize' => 'a4',
            ]
        );

        $document1->setConfig(
            [
                'pageOrientation' => 'portrait',
                'pageSize' => 'a4',
            ]
        );

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('search')->willReturn(
            new EntitySearchResult(
                'document',
                2,
                new DocumentCollection([$document1, $document2]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )
        );

        $mockFpdi = $this->createMock(Fpdi::class);

        $mockFpdi->expects($this->exactly(2))
            ->method('setSourceFile')
            ->willReturn(1);

        $mockFpdi->expects($this->exactly(2))
            ->method('importPage')
            ->willReturn('template');

        $mockFpdi->expects($this->exactly(2))
            ->method('getTemplateSize')
            ->willReturnOnConsecutiveCalls(
                ['0' => 420, '1' => 297, 'orientation' => 'L'],
                ['0' => 215.9, '1' => 279.4, 'orientation' => 'P']
            );

        $matcher = $this->exactly(2);
        $mockFpdi->expects($matcher)
            ->method('AddPage')
            ->willReturnCallback(static function ($orientation, $size) use ($matcher): void {
                $invocation = $matcher->numberOfInvocations();
                if ($invocation === 1) {
                    static::assertSame('L', $orientation, 'First call: orientation should be L');
                    static::assertSame([420, 297], $size, 'First call: size should match');
                } elseif ($invocation === 2) {
                    static::assertSame('P', $orientation, 'Second call: orientation should be P');
                    static::assertSame([215.9, 279.4], $size, 'Second call: size should match');
                } else {
                    static::fail('Unexpected call number');
                }
            });

        $mockFpdi->method('useTemplate');
        $mockFpdi->method('Output')->willReturn(self::PDF_CONTENT);

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('loadFileStream')
            ->willReturn(Utils::streamFor());

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            static::createStub(DocumentGenerator::class),
            $mockFpdi,
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        $result = $documentMerger->merge(
            [$document1->getId(), $document2->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
    }

    public function testMergeOneDocumentWithoutMediaFileUsesTheNameOfTheDocument(): void
    {
        $document = $this->createDocument(
            withMedia: true,
            config: [
                'filenamePrefix' => 'invoice_',
                'documentNumber' => '10000',
                'filenameSuffix' => '',
            ],
            withMediaFile: false,
        );

        $result = $this->mergeDocuments([$document]);

        static::assertNotNull($result);
        static::assertSame('invoice_10000.pdf', $result->getName());
    }

    public function testMergeOneDocumentWithoutMediaFileFallsBackToDocumentTypeAndNumber(): void
    {
        $document = $this->createDocument(withMedia: true, withMediaFile: false);
        $document->setDocumentNumber('10000');

        $result = $this->mergeDocuments([$document]);

        static::assertNotNull($result);
        static::assertSame('invoice_10000.pdf', $result->getName());
    }

    public function testMergeOneDocumentWithoutMediaFileSanitizesTheNameOfTheDocument(): void
    {
        $document = $this->createDocument(
            withMedia: true,
            config: [
                'filenamePrefix' => 'Rechnung /',
                'documentNumber' => '10 000',
                'filenameSuffix' => '',
            ],
            withMediaFile: false,
        );

        $result = $this->mergeDocuments([$document]);

        static::assertNotNull($result);
        static::assertSame('Rechnung-10-000.pdf', $result->getName());
    }

    public function testMergeOneDocumentWithoutAnyNameUsesDocumentTypeAndDate(): void
    {
        $document = $this->createDocument(withMedia: true, withMediaFile: false);

        $result = $this->mergeDocuments([$document]);

        static::assertNotNull($result);
        static::assertSame('invoice_' . self::DOWNLOAD_DATE . '.pdf', $result->getName());
    }

    public function testMergeMultipleDocumentsUsesTheConfiguredFileNamePrefixAndDate(): void
    {
        $firstDocument = $this->createDocument(
            withMedia: true,
            config: ['filenamePrefix' => 'Rechnung_', 'documentNumber' => '10000'],
        );
        $secondDocument = $this->createDocument(
            withMedia: true,
            config: ['filenamePrefix' => 'Rechnung_', 'documentNumber' => '10001'],
        );

        $result = $this->mergeDocuments([$firstDocument, $secondDocument]);

        static::assertNotNull($result);
        static::assertSame('Rechnung_' . self::DOWNLOAD_DATE . '.pdf', $result->getName());
    }

    public function testMergeMultipleDocumentsOfDifferentTypesUsesGenericNameAndDate(): void
    {
        $firstDocument = $this->createDocument(true);
        $secondDocument = $this->createDocument(withMedia: true, technicalName: 'delivery_note');

        $result = $this->mergeDocuments([$firstDocument, $secondDocument]);

        static::assertNotNull($result);
        static::assertSame('documents_' . self::DOWNLOAD_DATE . '.pdf', $result->getName());
    }

    /**
     * @param array<DocumentEntity> $documents
     */
    private function mergeDocuments(array $documents): ?RenderedDocument
    {
        $fpdi = static::createStub(Fpdi::class);
        $fpdi->method('setSourceFile')->willReturn(1);
        $fpdi->method('importPage')->willReturn('template');
        $fpdi->method('getTemplateSize')->willReturn(['0' => 210, '1' => 297, 'orientation' => 'P']);
        $fpdi->method('Output')->willReturn(self::PDF_CONTENT);

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('search')->willReturn(
            new EntitySearchResult(
                'document',
                \count($documents),
                new DocumentCollection($documents),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )
        );

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('loadFile')->willReturn(self::PDF_CONTENT);
        $mediaService->method('loadFileStream')->willReturn(Utils::streamFor());

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            static::createStub(DocumentGenerator::class),
            $fpdi,
            static::createStub(Filesystem::class),
            new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'),
        );

        return $documentMerger->merge(
            array_map(static fn (DocumentEntity $document): string => $document->getId(), $documents),
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createDocument(
        bool $withMedia,
        bool $withDocumentType = true,
        string $fileExtension = 'pdf',
        string $mimeType = 'application/pdf',
        string $fileName = 'document',
        array $config = [],
        string $technicalName = 'invoice',
        bool $withMediaFile = true,
    ): DocumentEntity {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrderId(Uuid::randomHex());
        $document->setStatic(false);
        $document->setConfig($config);

        if ($withDocumentType) {
            $documentType = new DocumentTypeEntity();
            $documentType->setId(Uuid::randomHex());
            $documentType->setTechnicalName($technicalName);
            $document->setDocumentTypeId($documentType->getId());
            $document->setDocumentType($documentType);
            $document->setTypeName($technicalName);
        }

        if ($withMedia) {
            $mediaEntity = new MediaEntity();
            $mediaEntity->setId(Uuid::randomHex());
            $mediaEntity->setFileExtension($fileExtension);
            $mediaEntity->setMimeType($mimeType);
            $mediaEntity->setFileName($fileName);
            $document->setDocumentMediaFileId($mediaEntity->getId());

            // The media file may exist without its association being loaded
            if ($withMediaFile) {
                $document->setDocumentMediaFile($mediaEntity);
            }
        }

        return $document;
    }
}
