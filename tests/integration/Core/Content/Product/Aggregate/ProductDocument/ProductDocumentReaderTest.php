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

    private ProductDocumentReader $reader;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->reader = new ProductDocumentReader(static::getContainer()->get(Connection::class));
    }

    public function testItReadsTheFiles(): void
    {
        $this->createMedia('manual', path: 'https://cdn.example.com/manual.pdf');
        $this->createMedia('upload', extension: null, size: null, private: true);

        $files = $this->reader->getFiles([$this->ids->get('manual'), $this->ids->get('upload'), Uuid::randomHex()]);

        $expected = [
            $this->ids->get('manual') => ['extension' => 'pdf', 'size' => 1024, 'private' => false, 'path' => 'https://cdn.example.com/manual.pdf'],
            $this->ids->get('upload') => ['extension' => null, 'size' => null, 'private' => true, 'path' => null],
        ];
        ksort($expected);
        ksort($files);
        static::assertSame($expected, $files);
    }

    public function testItCountsTheDocumentsOfEachProduct(): void
    {
        $this->createMedia('file');
        $this->createProduct('drill', documents: ['Manual', 'Datasheet']);
        $this->createProduct('hammer', documents: ['Certificate']);
        $this->createProduct('screwdriver');

        $counts = $this->reader->countDocuments([$this->key('drill'), $this->key('screwdriver')]);

        static::assertSame([$this->key('drill') => 2], $counts);
    }

    private function createMedia(string $fileName, ?string $extension = 'pdf', ?int $size = 1024, bool $private = false, ?string $path = null): void
    {
        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->create($fileName),
            'fileName' => $fileName,
            'fileExtension' => $extension,
            'fileSize' => $size,
            'private' => $private,
            'path' => $path,
        ]], Context::createDefaultContext());
    }

    /**
     * @param list<string> $documents titles of documents that all use the file "file"
     */
    private function createProduct(string $product, array $documents = []): void
    {
        $payload = (new ProductBuilder($this->ids, $product))->price(10)->build();
        $payload['documents'] = array_map(fn (string $title): array => ['title' => $title, 'mediaId' => $this->ids->get('file')], $documents);

        static::getContainer()->get('product.repository')->create([$payload], Context::createDefaultContext());
    }

    private function key(string $name): string
    {
        return $this->ids->get($name) . Defaults::LIVE_VERSION;
    }
}
