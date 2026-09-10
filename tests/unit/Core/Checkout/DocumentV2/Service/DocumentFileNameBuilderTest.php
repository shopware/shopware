<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileNameBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentFileNameBuilder::class)]
class DocumentFileNameBuilderTest extends TestCase
{
    private const DOWNLOAD_DATE = '2026-01-15';

    public function testBuildUsesTheNameOfASingleDocument(): void
    {
        $document = $this->createDocument(config: [
            'filenamePrefix' => 'invoice_',
            'documentNumber' => '10000',
            'filenameSuffix' => '',
        ]);

        static::assertSame('invoice_10000', $this->build([$document]));
    }

    public function testBuildAppendsTheConfiguredFileNameSuffix(): void
    {
        $document = $this->createDocument(config: [
            'filenamePrefix' => 'invoice_',
            'documentNumber' => '10000',
            'filenameSuffix' => '_foo',
        ]);

        static::assertSame('invoice_10000_foo', $this->build([$document]));
    }

    public function testBuildFallsBackToTheDocumentTypeAndNumberOfASingleDocument(): void
    {
        $document = $this->createDocument();
        $document->setDocumentNumber('10000');

        static::assertSame('invoice_10000', $this->build([$document]));
    }

    public function testBuildSanitizesTheNameOfASingleDocument(): void
    {
        $document = $this->createDocument(config: [
            'filenamePrefix' => 'Rechnung /',
            'documentNumber' => '10 000',
            'filenameSuffix' => '',
        ]);

        static::assertSame('Rechnung-10-000', $this->build([$document]));
    }

    public function testBuildFallsBackToTheDocumentTypeAndDateWithoutADocumentNumber(): void
    {
        $document = $this->createDocument();

        static::assertSame('invoice_' . self::DOWNLOAD_DATE, $this->build([$document]));
    }

    public function testBuildUsesTheConfiguredFileNamePrefixAndDateForMultipleDocuments(): void
    {
        $documents = [
            $this->createDocument(config: ['filenamePrefix' => 'Rechnung_', 'documentNumber' => '10000']),
            $this->createDocument(config: ['filenamePrefix' => 'Rechnung_', 'documentNumber' => '10001']),
        ];

        static::assertSame('Rechnung_' . self::DOWNLOAD_DATE, $this->build($documents));
    }

    public function testBuildUsesTheDocumentTypeAndDateForAmbiguousFileNamePrefixes(): void
    {
        $documents = [
            $this->createDocument(config: ['filenamePrefix' => 'Rechnung_', 'documentNumber' => '10000']),
            $this->createDocument(config: ['filenamePrefix' => 'Invoice_', 'documentNumber' => '10001']),
        ];

        static::assertSame('invoice_' . self::DOWNLOAD_DATE, $this->build($documents));
    }

    public function testBuildUsesAGenericNameAndDateForMixedDocumentTypes(): void
    {
        $documents = [
            $this->createDocument(),
            $this->createDocument(typeName: 'delivery_note'),
        ];

        static::assertSame('documents_' . self::DOWNLOAD_DATE, $this->build($documents));
    }

    public function testBuildUsesAGenericNameAndDateWithoutADocumentType(): void
    {
        $documents = [
            $this->createDocument(typeName: null),
            $this->createDocument(typeName: null),
        ];

        static::assertSame('documents_' . self::DOWNLOAD_DATE, $this->build($documents));
    }

    public function testBuildUsesAGenericNameAndDateForAnEmptyCollection(): void
    {
        static::assertSame('documents_' . self::DOWNLOAD_DATE, $this->build([]));
    }

    /**
     * @param array<DocumentEntity> $documents
     */
    private function build(array $documents): string
    {
        $builder = new DocumentFileNameBuilder(new MockClock(self::DOWNLOAD_DATE . ' 10:00:00'));

        return $builder->build(new DocumentCollection($documents));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createDocument(array $config = [], ?string $typeName = 'invoice'): DocumentEntity
    {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig($config);
        $document->setTypeName($typeName);

        return $document;
    }
}
