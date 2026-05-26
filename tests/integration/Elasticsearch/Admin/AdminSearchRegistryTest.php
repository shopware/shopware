<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class AdminSearchRegistryTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private AdminSearchRegistry $registry;

    private Client $client;

    protected function setUp(): void
    {
        $this->clearElasticsearch();

        $this->connection = static::getContainer()->get(Connection::class);

        $this->client = static::getContainer()->get(Client::class);

        $indexer = new PromotionAdminSearchIndexer(
            $this->connection,
            static::getContainer()->get(IteratorFactory::class),
            static::getContainer()->get('promotion.repository'),
            static::getContainer()->get(ElasticsearchFieldBuilder::class),
            100
        );

        $searchHelper = new AdminElasticsearchHelper(true, true, 'sw-admin', 'test', true, $this->createMock(LoggerInterface::class));
        $this->registry = new AdminSearchRegistry(
            ['promotion' => $indexer],
            $this->connection,
            $this->getDiContainer()->get(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->client,
            $searchHelper,
            $this->createMock(LoggerInterface::class),
            [
                'settings' => [
                    'analysis' => [
                        'normalizer' => [
                            'sw_lowercase_normalizer' => [
                                'type' => 'custom',
                                'filter' => ['lowercase'],
                            ],
                        ],
                        'char_filter' => [
                            'sw_decimal_normalize' => [
                                'type' => 'pattern_replace',
                                'pattern' => '(\\d),(\\d)',
                                'replacement' => '$1.$2',
                            ],
                            'sw_unit_glue' => [
                                'type' => 'pattern_replace',
                                'pattern' => '(^|\\s)(\\d+(?:[./,\'\\-]\\d+)*)\\s+([^\\d\\s])',
                                'replacement' => '$1$2$3',
                            ],
                        ],
                        'analyzer' => [
                            'sw_whitespace_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'whitespace',
                                'filter' => ['lowercase'],
                            ],
                            'sw_ngram_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'whitespace',
                                'filter' => [
                                    'lowercase',
                                    'sw_ngram_filter',
                                ],
                            ],
                            'sw_admin_completion_index_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'whitespace',
                                'char_filter' => ['sw_decimal_normalize', 'sw_unit_glue'],
                                'filter' => ['sw_word_delimiter_filter', 'flatten_graph', 'lowercase', 'sw_length_min', 'remove_duplicates'],
                            ],
                            'sw_admin_completion_search_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'whitespace',
                                'char_filter' => ['sw_decimal_normalize', 'sw_unit_glue'],
                                'filter' => ['sw_word_delimiter_filter', 'lowercase', 'sw_length_min', 'remove_duplicates', 'sw_unique_filter'],
                            ],
                        ],
                        'filter' => [
                            'sw_ngram_filter' => [
                                'type' => 'ngram',
                                'min_gram' => 4,
                                'max_gram' => 5,
                            ],
                            'sw_word_delimiter_filter' => [
                                'type' => 'word_delimiter_graph',
                                'preserve_original' => true,
                                'catenate_all' => true,
                                'catenate_words' => true,
                                'catenate_numbers' => true,
                                'split_on_case_change' => true,
                                'generate_word_parts' => true,
                                'split_on_numerics' => true,
                            ],
                            'sw_length_min' => [
                                'type' => 'length',
                                'min' => 2,
                            ],
                            'sw_unique_filter' => [
                                'type' => 'unique',
                                'only_on_same_position' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [],
            'test'
        );
    }

    public function testIterate(): void
    {
        $c = static::getContainer()->get(Connection::class);
        static::assertEmpty($c->fetchAllAssociative('SELECT `index` FROM `admin_elasticsearch_index_task`'));

        $this->registry->iterate(new AdminIndexingBehavior(true));

        $index = $c->fetchOne('SELECT `index` FROM `admin_elasticsearch_index_task`');

        static::assertNotFalse($index);

        static::assertTrue($this->client->indices()->exists(['index' => $index]));

        $indices = array_values($this->client->indices()->getMapping(['index' => $index]))[0];
        $properties = $indices['mappings']['properties'];

        // Assert base properties exist
        static::assertArrayHasKey('id', $properties);
        static::assertArrayHasKey('text', $properties);
        static::assertArrayHasKey('entityName', $properties);
        static::assertArrayHasKey('parameters', $properties);
        static::assertArrayHasKey('textBoosted', $properties);

        if (Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            // Assert promotion-specific properties from mapping()
            static::assertArrayHasKey('active', $properties);
            static::assertArrayHasKey('name', $properties);
            static::assertArrayHasKey('validFrom', $properties);
            static::assertArrayHasKey('validUntil', $properties);
            static::assertArrayHasKey('createdAt', $properties);
        }
    }

    public function testRefresh(): void
    {
        $c = static::getContainer()->get(Connection::class);
        static::assertEmpty($c->fetchAllAssociative('SELECT `index` FROM `admin_elasticsearch_index_task`'));

        $this->registry->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult(
                    'c1a28776116d4431a2208eb2960ec340',
                    [],
                    'promotion',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], Context::createDefaultContext()),
        ]), []));

        $this->runWorker();

        $index = $c->fetchOne('SELECT `index` FROM `admin_elasticsearch_index_task`');

        static::assertNotFalse($index);

        static::assertTrue($this->client->indices()->exists(['index' => $index]));

        $indices = array_values($this->client->indices()->getMapping(['index' => $index]))[0];
        $properties = $indices['mappings']['properties'];

        // Assert base properties exist
        static::assertArrayHasKey('id', $properties);
        static::assertArrayHasKey('text', $properties);
        static::assertArrayHasKey('entityName', $properties);
        static::assertArrayHasKey('parameters', $properties);
        static::assertArrayHasKey('textBoosted', $properties);

        if (Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            // Assert promotion-specific properties from mapping()
            static::assertArrayHasKey('active', $properties);
            static::assertArrayHasKey('name', $properties);
            static::assertArrayHasKey('validFrom', $properties);
            static::assertArrayHasKey('validUntil', $properties);
            static::assertArrayHasKey('createdAt', $properties);
        }
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }
}
