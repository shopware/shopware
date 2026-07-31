<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentFileResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentFileResolver::class)]
class DocumentFileResolverTest extends TestCase
{
    public function testLoadDocumentReturnsDocument(): void
    {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());

        $resolver = $this->createResolver([$document]);

        $result = $resolver->loadDocument($document->getId(), Context::createDefaultContext());

        static::assertSame($document, $result);
    }

    public function testLoadDocumentReturnsNullWhenNotFound(): void
    {
        $resolver = $this->createResolver([]);

        $result = $resolver->loadDocument(Uuid::randomHex(), Context::createDefaultContext());

        static::assertNull($result);
    }

    public function testFindMediaByFormatReturnsMatchingMedia(): void
    {
        $pdfMedia = new MediaEntity();
        $pdfMedia->setId(Uuid::randomHex());

        $htmlMedia = new MediaEntity();
        $htmlMedia->setId(Uuid::randomHex());

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile(DocumentFormat::HTML->value, $htmlMedia),
            $this->createDocumentFile(DocumentFormat::PDF->value, $pdfMedia),
        ]));

        $resolver = $this->createResolver([]);

        static::assertSame($pdfMedia, $resolver->findMediaByFormat($document, DocumentFormat::PDF->value));
        static::assertSame($htmlMedia, $resolver->findMediaByFormat($document, DocumentFormat::HTML->value));
    }

    public function testFindMediaByFormatReturnsNullWhenFormatIsUnavailable(): void
    {
        $htmlMedia = new MediaEntity();
        $htmlMedia->setId(Uuid::randomHex());

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile(DocumentFormat::HTML->value, $htmlMedia),
        ]));

        $resolver = $this->createResolver([]);

        static::assertNull($resolver->findMediaByFormat($document, DocumentFormat::PDF->value));
    }

    public function testFindMediaByFormatReturnsNullWithoutDocumentFiles(): void
    {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());

        $resolver = $this->createResolver([]);

        static::assertNull($resolver->findMediaByFormat($document, DocumentFormat::PDF->value));
    }

    /**
     * @param list<DocumentEntity> $documents
     */
    private function createResolver(array $documents): DocumentFileResolver
    {
        $context = Context::createDefaultContext();

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'document',
                \count($documents),
                new DocumentCollection($documents),
                null,
                new Criteria(),
                $context,
            ),
        ]);

        return new DocumentFileResolver($documentRepository);
    }

    private function createDocumentFile(string $format, MediaEntity $media): DocumentFileEntity
    {
        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($media->getId());
        $documentFile->setMedia($media);

        return $documentFile;
    }
}
