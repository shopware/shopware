<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;

/**
 * @package system-settings
 *
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Admin\AdminSearcher
 */
class AdminSearcherTest extends TestCase
{
    private MockObject $client;

    private AdminSearcher $searcher;

    public function setUp(): void
    {
        $this->client = $this->createMock(Client::class);

        $registry = $this->getMockBuilder(AdminSearchRegistry::class)->disableOriginalConstructor()->getMock();

        $indexer = new ProductAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );
        $registry->method('getIndexers')->willReturn(['product' => $indexer]);
        $registry->method('getIndexer')->willReturn($indexer);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'sw-admin');
        $this->searcher = new AdminSearcher($this->client, $registry, $searchHelper);
    }

    public function testElasticSearch(): void
    {
        $this->client
            ->expects(static::once())
            ->method('msearch')
            ->with([
                'body' => [
                    [
                        'index' => 'sw-admin-product-listing',
                    ],
                    [
                        'query' => [
                            'bool' => [
                                'should' => [
                                    [
                                        'simple_query_string' => [
                                            'query' => 'elasticsearch*',
                                            'fields' => ['text'],
                                        ],
                                    ],
                                    [
                                        'simple_query_string' => [
                                            'query' => 'elasticsearch*',
                                            'fields' => ['textBoosted'],
                                            'boost' => 10,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'size' => 5,
                    ],
                ],
            ])->willReturn([
                'took' => 42,
                'responses' => [
                    [
                        'took' => 42,
                        'timed_out' => false,
                        '_shards' => [
                            'total' => 1,
                            'successful' => 1,
                            'skipped' => 0,
                            'failed' => 0,
                        ],
                        'hits' => [
                            'total' => [
                                'value' => 1,
                                'relation' => 'eq',
                            ],
                            'max_score' => 4.9525366,
                            'hits' => [
                                [
                                    '_index' => 'sw-admin-product-listing',
                                    '_type' => '_doc',
                                    '_id' => 'c1a28776116d4431a2208eb2960ec340',
                                    '_score' => 4.9525366,
                                    '_source' => [
                                        'entityName' => 'product',
                                        'parameters' => [],
                                        'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
                                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                                    ],
                                ],
                            ],
                        ],
                        'status' => 200,
                    ],
                ],
            ]);

        $data = $this->searcher->search('elasticsearch', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($data['product']);

        static::assertEquals(1, $data['product']['total']);
        static::assertEquals('product-listing', $data['product']['indexer']);
        static::assertEquals('sw-admin-product-listing', $data['product']['index']);
    }
}
