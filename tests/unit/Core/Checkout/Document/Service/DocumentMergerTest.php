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
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentMerger::class)]
class DocumentMergerTest extends TestCase
{
    public const PDF_CONTENT = 'PDF content for testing';

    public function testMergeOneDocument(): void
    {
        $document = $this->createDocument(true);

        $fpdi = $this->createMock(Fpdi::class);
        $fpdi->expects($this->never())->method('setSourceFile');

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->expects($this->never())->method('generate');

        $mediaService = $this->createMock(MediaService::class);
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
            $this->createMock(Fpdi::class),
        );

        $result = $documentMerger->merge(
            [$document->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('pdf', $result->getFileExtension());
        static::assertSame(self::PDF_CONTENT, $result->getContent());
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

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
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

        $documentGenerator = $this->createMock(DocumentGenerator::class);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFileStream')
            ->willReturnCallback(function () {
                return Utils::streamFor();
            });

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            $documentGenerator,
            $fpdi,
        );

        $result = $documentMerger->merge(
            [$firstDocument->getId(), $secondDocument->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('pdf', $result->getFileExtension());
        static::assertSame(self::PDF_CONTENT, $result->getContent());
    }

    public function testMergeTriggersDocumentGenerationWhenMediaMissing(): void
    {
        $document = $this->createDocument(false);

        $documentWithMedia = clone $document;
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId(Uuid::randomHex());
        $mediaEntity->setFileExtension('pdf');
        $documentWithMedia->setDocumentMediaFileId($mediaEntity->getId());
        $documentWithMedia->setDocumentMediaFile($mediaEntity);

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository->expects($this->exactly(2))->method('search')->willReturnOnConsecutiveCalls(
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
        $documentGenerator->expects($this->exactly(1))->method('generate')->willReturnCallback(function (string $documentType, array $operations) {
            $ids = array_keys($operations);
            $result = new DocumentGenerationResult();

            $result->addSuccess(new DocumentIdStruct($ids[0], '', Uuid::randomHex()));

            return $result;
        });

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $this->createMock(MediaService::class),
            $documentGenerator,
            $this->createMock(Fpdi::class),
        );

        $result = $documentMerger->merge(
            [$document->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('pdf', $result->getFileExtension());
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

        /** @var StaticEntityRepository<DocumentCollection> $documentRepository */
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
            ->willReturnCallback(function () {
                return Utils::streamFor();
            });

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            $this->createMock(DocumentGenerator::class),
            $fpdi,
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

        $documentRepository = $this->createMock(EntityRepository::class);
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
        $fpdi->method('setSourceFile')->willThrowException(new FpdiException('PDF merge failed'));

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $this->createMock(MediaService::class),
            $this->createMock(DocumentGenerator::class),
            $fpdi
        );

        $result = $documentMerger->merge(
            [$firstDocument->getId(), $secondDocument->getId()],
            Context::createDefaultContext()
        );

        static::assertNotNull($result);
        static::assertSame('zip', $result->getFileExtension());
        static::assertSame('application/zip', $result->getContentType());
        static::assertNotEmpty($result->getContent());
    }

    public function testDocumentGenerationFailsReturnsNull(): void
    {
        $document = $this->createDocument(false);

        $documentRepository = $this->createMock(EntityRepository::class);
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

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->method('generate')
            ->willReturn(new DocumentGenerationResult());

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $this->createMock(MediaService::class),
            $documentGenerator,
            $this->createMock(Fpdi::class)
        );

        $result = $documentMerger->merge(
            [$document->getId()],
            Context::createDefaultContext()
        );

        static::assertNull($result);
    }

    private function createDocument(bool $withMedia, bool $withDocumentType = true): DocumentEntity
    {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrderId(Uuid::randomHex());
        $document->setStatic(false);
        $document->setConfig([]);

        if ($withDocumentType) {
            $documentType = new DocumentTypeEntity();
            $documentType->setId(Uuid::randomHex());
            $documentType->setTechnicalName('invoice');
            $document->setDocumentTypeId($documentType->getId());
            $document->setDocumentType($documentType);
        }

        if ($withMedia) {
            $mediaEntity = new MediaEntity();
            $mediaEntity->setId(Uuid::randomHex());
            $mediaEntity->setFileExtension('pdf');
            $document->setDocumentMediaFile($mediaEntity);
            $document->setDocumentMediaFileId($mediaEntity->getId());
        }

        return $document;
    }
}
