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
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;

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

        $indexer = new PromotionAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );
        $registry->method('getIndexers')->willReturn([$indexer]);
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
                        'index' => 'sw-admin-promotion-listing',
                    ],
                    [
                        'query' => [
                            'query_string' => [
                                'query' => 'elasticsearch',
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
                                    '_index' => 'sw-admin-promotion-listing',
                                    '_type' => '_doc',
                                    '_id' => 'c1a28776116d4431a2208eb2960ec340',
                                    '_score' => 4.9525366,
                                    '_source' => [
                                        'entityName' => 'promotion',
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

        $data = $this->searcher->search('elasticsearch', ['promotion'], Context::createDefaultContext());

        static::assertNotEmpty($data['promotion']);

        static::assertEquals(1, $data['promotion']['total']);
        static::assertEquals('promotion-listing', $data['promotion']['indexer']);
        static::assertEquals('sw-admin-promotion-listing', $data['promotion']['index']);
    }
}
