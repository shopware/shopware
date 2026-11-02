<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use OpenSearch\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
class ProductPriceMappingUpdateTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        $this->clearElasticsearch();
    }

    protected function tearDown(): void
    {
        $this->clearElasticsearch();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function indexedPriceTypeProvider(): iterable
    {
        yield 'price detected as float before the mapping update' => ['float'];
        yield 'price mapped per currency as double' => ['double'];
    }

    #[DataProvider('indexedPriceTypeProvider')]
    public function testMappingUpdateKeepsAnIndexedPrice(string $indexedType): void
    {
        $client = static::getContainer()->get(Client::class);
        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        static::assertNotNull($indexer);
        $indexer->createIndices();

        $alias = static::getContainer()->get(ElasticsearchHelper::class)->getIndexName(static::getContainer()->get(ProductDefinition::class));
        $indexedCurrency = 'c_' . Defaults::CURRENCY;

        $client->indices()->putMapping([
            'index' => $alias,
            'body' => ['properties' => ['price' => ['properties' => [$indexedCurrency => ['properties' => [
                'gross' => ['type' => $indexedType],
                'net' => ['type' => $indexedType],
            ]]]]]],
        ]);

        static::getContainer()->get(IndexMappingUpdater::class)->update(Context::createDefaultContext());

        $newCurrency = 'c_' . Uuid::randomHex();
        $client->index([
            'index' => $alias,
            'id' => Uuid::randomHex(),
            'refresh' => true,
            'body' => ['price' => [$newCurrency => ['gross' => 10.99, 'net' => 9.24]]],
        ]);

        $mapping = $client->indices()->getMapping(['index' => $alias]);
        $priceMapping = array_values($mapping)[0]['mappings']['properties']['price']['properties'];

        static::assertSame($indexedType, $priceMapping[$indexedCurrency]['properties']['gross']['type']);
        static::assertSame($indexedType, $priceMapping[$indexedCurrency]['properties']['net']['type']);
        static::assertSame('double', $priceMapping[$newCurrency]['properties']['gross']['type']);
        static::assertSame('double', $priceMapping[$newCurrency]['properties']['net']['type']);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }
}
