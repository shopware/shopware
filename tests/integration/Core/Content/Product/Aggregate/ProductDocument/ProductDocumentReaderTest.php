<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentReader;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentReaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private Connection $connection;

    private ProductDocumentReader $reader;

    /**
     * @var array<string, true>
     */
    private array $products = [];

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->reader = new ProductDocumentReader($this->connection);
    }

    public function testItReadsTheFiles(): void
    {
        $this->createMedia('manual');
        $this->createMedia('upload', extension: null, size: null, private: true);

        $files = $this->reader->getFiles([$this->ids->get('manual'), $this->ids->get('upload'), Uuid::randomHex()]);

        $expected = [
            $this->ids->get('manual') => ['extension' => 'pdf', 'size' => 1024, 'private' => false],
            $this->ids->get('upload') => ['extension' => null, 'size' => null, 'private' => true],
        ];
        ksort($expected);
        ksort($files);
        static::assertSame($expected, $files);
    }

    public function testItCountsTheDocumentsOfEachProduct(): void
    {
        $this->createMedia('file');
        $this->insertDocument('drill', 'manual', [Defaults::LANGUAGE_SYSTEM => 'file']);
        $this->insertDocument('drill', 'datasheet', [Defaults::LANGUAGE_SYSTEM => 'file']);
        $this->insertDocument('hammer', 'certificate', [Defaults::LANGUAGE_SYSTEM => 'file']);
        $this->createProduct('screwdriver');

        $counts = $this->reader->countDocuments([$this->key('drill'), $this->key('screwdriver')]);

        static::assertSame([$this->key('drill') => 2], $counts);
    }

    private function createMedia(string $fileName, ?string $extension = 'pdf', ?int $size = 1024, bool $private = false): void
    {
        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->create($fileName),
            'fileName' => $fileName,
            'fileExtension' => $extension,
            'fileSize' => $size,
            'private' => $private,
        ]], Context::createDefaultContext());
    }

    private function createProduct(string $product): void
    {
        if (isset($this->products[$product])) {
            return;
        }

        $this->products[$product] = true;
        static::getContainer()->get('product.repository')->create([(new ProductBuilder($this->ids, $product))->price(10)->build()], Context::createDefaultContext());
    }

    /**
     * Stores a document past the validator.
     *
     * @param array<string, string> $files the file of each language
     */
    private function insertDocument(string $product, string $document, array $files): void
    {
        $this->createProduct($product);
        $createdAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('product_document', [
            'id' => $this->ids->getBytes($document),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'product_id' => $this->ids->getBytes($product),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'created_at' => $createdAt,
        ]);

        foreach ($files as $languageId => $file) {
            $this->connection->insert('product_document_translation', [
                'product_document_id' => $this->ids->getBytes($document),
                'product_document_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'language_id' => Uuid::fromHexToBytes($languageId),
                'media_id' => $this->ids->getBytes($file),
                'created_at' => $createdAt,
            ]);
        }
    }

    private function key(string $name): string
    {
        return $this->ids->get($name) . Defaults::LIVE_VERSION;
    }
}
