<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Tfpdf\Fpdi;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentGenerationResult;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DocumentMerger::class)]
class DocumentMergerTest extends TestCase
{
    public function testMergeWithFpdiConfig(): void
    {
        $fpdi = $this->createMock(Fpdi::class);
        $fpdi->expects(static::exactly(1))
            ->method('setSourceFile');

        $orderId = Uuid::randomHex();

        $documentType = new DocumentTypeEntity();
        $documentType->setId(Uuid::randomHex());
        $documentType->setTechnicalName('invoice');

        $firstDocument = new DocumentEntity();
        $firstDocument->setId(Uuid::randomHex());
        $firstDocument->setOrderId($orderId);
        $firstDocument->setDocumentTypeId($documentType->getId());
        $firstDocument->setDocumentType($documentType);
        $firstDocument->setStatic(false);
        $firstDocument->setConfig([]);

        $secondDocument = new DocumentEntity();
        $secondDocument->setId(Uuid::randomHex());
        $secondDocument->setOrderId($orderId);
        $secondDocument->setStatic(false);
        $secondDocument->setConfig([]);

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
        $documentGenerator->expects(static::exactly(1))->method('generate')->willReturnCallback(function (string $documentType, array $operations) {
            $ids = array_keys($operations);
            $result = new DocumentGenerationResult();

            $result->addSuccess(new DocumentIdStruct($ids[0], '', Uuid::randomHex()));

            return $result;
        });

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects(static::once())
            ->method('loadFileStream')
            ->willReturnCallback(function () {
                return Utils::streamFor();
            });

        $documentMerger = new DocumentMerger(
            $documentRepository,
            $mediaService,
            $documentGenerator,
            $fpdi
        );

        $documentMerger->merge([Uuid::randomHex()], Context::createDefaultContext());
    }
}
