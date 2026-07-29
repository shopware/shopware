<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class ProductCustomFieldsUsedUpdaterTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    private Client $client;

    private ElasticsearchOutdatedIndexDetector $indexDetector;

    private IdsCollection $ids;

    private Connection $connection;

    /**
     * The ES index is created once for the whole class by the first run of setUp() and shared across
     * tests (this class has no transaction isolation). The first-test-creates-the-index pattern was
     * replaced by guarded setUp so the suite no longer depends on test execution order.
     */
    private static bool $indexReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->client = static::getContainer()->get(Client::class);
        $this->indexDetector = static::getContainer()->get(ElasticsearchOutdatedIndexDetector::class);
        $this->connection = static::getContainer()->get(Connection::class);

        if (!self::$indexReady) {
            $this->createIndex();
            self::$indexReady = true;
        }
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM product_sorting WHERE url_key LIKE :key', ['key' => 'test-sorting-%']);
        $this->connection->executeStatement('DELETE FROM product_stream_filter WHERE field LIKE :field', ['field' => 'customFields.sorting_stream_test_%']);

        // Clean up streams
        if ($this->ids->has('stream-1')) {
            $this->connection->executeStatement(
                'DELETE FROM product_stream WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($this->ids->get('stream-1'))]
            );
        }

        if ($this->ids->has('custom-field-set')) {
            static::getContainer()->get('custom_field_set.repository')->delete([
                ['id' => $this->ids->get('custom-field-set')],
            ], Context::createDefaultContext());
        }

        parent::tearDown();
    }

    public function testCreateIndicesWithElasticsearchEnabled(): void
    {
        static::assertNotEmpty($this->indexDetector->getAllUsedIndices());
    }

    public function testProductSortingWithCustomFieldCreatesMappingWhenEnabled(): void
    {
        $this->createCustomFields();

        $sortingRepository = static::getContainer()->get('product_sorting.repository');

        $sortingRepository->create([
            [
                'id' => $this->ids->get('sorting-1'),
                'key' => 'test-sorting-int',
                'priority' => 1,
                'active' => true,
                'fields' => [
                    ['field' => 'customFields.sorting_stream_test_int', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ],
                'label' => 'Sort by test int',
            ],
        ], Context::createDefaultContext());

        $indexName = array_keys($this->indexDetector->getAllUsedIndices())[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'] ?? [];

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $properties);
        $languageProperties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'];
        static::assertIsArray($languageProperties);
        static::assertArrayHasKey('sorting_stream_test_int', $languageProperties);
        static::assertSame('long', $languageProperties['sorting_stream_test_int']['type']);
    }

    public function testProductSortingWithMultipleCustomFieldTypes(): void
    {
        $this->createCustomFields();

        $sortingRepository = static::getContainer()->get('product_sorting.repository');

        $sortingRepository->create([
            [
                'id' => $this->ids->get('sorting-2'),
                'key' => 'test-sorting-float',
                'priority' => 2,
                'active' => true,
                'fields' => [
                    ['field' => 'customFields.sorting_stream_test_float', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => false],
                    ['field' => 'customFields.sorting_stream_test_text', 'order' => 'asc', 'priority' => 0, 'naturalSorting' => false],
                ],
                'label' => 'Sort by test float and text',
            ],
        ], Context::createDefaultContext());

        $indexName = array_keys($this->indexDetector->getAllUsedIndices())[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'] ?? [];

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $properties);
        $languageProperties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'];
        static::assertIsArray($languageProperties);

        static::assertArrayHasKey('sorting_stream_test_float', $languageProperties);
        static::assertSame('double', $languageProperties['sorting_stream_test_float']['type']);

        static::assertArrayHasKey('sorting_stream_test_text', $languageProperties);
        static::assertSame('keyword', $languageProperties['sorting_stream_test_text']['type']);
    }

    public function testProductStreamFilterWithCustomFieldCreatesMapping(): void
    {
        $this->createCustomFields();

        $productStreamRepository = static::getContainer()->get('product_stream.repository');

        $productStreamRepository->create([
            [
                'id' => $this->ids->get('stream-1'),
                'name' => 'Test Stream',
                'filters' => [
                    [
                        'id' => $this->ids->get('filter-1'),
                        'type' => 'equals',
                        'field' => 'customFields.sorting_stream_test_bool',
                        'value' => '1',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $indexName = array_keys($this->indexDetector->getAllUsedIndices())[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'] ?? [];

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $properties);
        $languageProperties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'];
        static::assertIsArray($languageProperties);

        static::assertArrayHasKey('sorting_stream_test_bool', $languageProperties);
        static::assertSame('boolean', $languageProperties['sorting_stream_test_bool']['type']);
    }

    public function testProductSortingDoesNotCreateMappingWhenDisabledElasticsearch(): void
    {
        $this->createCustomFields();

        $this->disableElasticsearch();

        $sortingRepository = static::getContainer()->get('product_sorting.repository');

        $sortingRepository->create([
            [
                'id' => $this->ids->get('sorting-disabled'),
                'key' => 'test-sorting-disabled',
                'priority' => 10,
                'active' => true,
                'fields' => [
                    ['field' => 'customFields.sorting_stream_test_datetime', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ],
                'label' => 'Sort by test datetime (disabled ES)',
            ],
        ], Context::createDefaultContext());

        $this->enableElasticsearch();

        $indexName = array_keys($this->indexDetector->getAllUsedIndices())[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'] ?? [];

        // ES was disabled while the sorting/stream was created, so the field must not be mapped -
        // independent of whether an enabled test already established the customFields mapping structure.
        $languageProperties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'] ?? [];

        static::assertArrayNotHasKey('sorting_stream_test_datetime', $languageProperties);
    }

    public function testProductStreamFilterDoesNotCreateMappingWhenDisabledElasticsearch(): void
    {
        $this->createCustomFields();

        $this->disableElasticsearch();

        $productStreamRepository = static::getContainer()->get('product_stream.repository');

        $productStreamRepository->create([
            [
                'id' => $this->ids->get('stream-1'),
                'name' => 'Test Stream Disabled',
                'filters' => [
                    [
                        'id' => $this->ids->get('filter-disabled'),
                        'type' => 'equals',
                        'field' => 'customFields.sorting_stream_test_datetime',
                        'value' => '2024-01-01',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->enableElasticsearch();

        $indexName = array_keys($this->indexDetector->getAllUsedIndices())[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'] ?? [];

        // ES was disabled while the sorting/stream was created, so the field must not be mapped -
        // independent of whether an enabled test already established the customFields mapping structure.
        $languageProperties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'] ?? [];

        static::assertArrayNotHasKey('sorting_stream_test_datetime', $languageProperties);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }

    private function createIndex(): void
    {
        $this->clearElasticsearch();

        $this->connection->executeStatement('DELETE FROM custom_field');

        // Create the ES index first (empty, no custom fields yet)
        $command = new ElasticsearchIndexingCommand(
            static::getContainer()->get(ElasticsearchIndexer::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(CreateAliasTaskHandler::class),
            true
        );

        $command->run(new ArrayInput([]), new NullOutput());
    }

    private function createCustomFields(): void
    {
        // Check if custom fields already exist
        $existingCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM custom_field WHERE name LIKE :name',
            ['name' => 'sorting_stream_test_%']
        );

        if ($existingCount > 0) {
            return;
        }

        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('custom-field-set'),
                'name' => 'sorting_stream_test_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'Sorting Stream Test Set',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'name' => 'sorting_stream_test_int',
                        'type' => CustomFieldTypes::INT,
                    ],
                    [
                        'name' => 'sorting_stream_test_float',
                        'type' => CustomFieldTypes::FLOAT,
                    ],
                    [
                        'name' => 'sorting_stream_test_text',
                        'type' => CustomFieldTypes::TEXT,
                    ],
                    [
                        'name' => 'sorting_stream_test_bool',
                        'type' => CustomFieldTypes::BOOL,
                    ],
                    [
                        'name' => 'sorting_stream_test_datetime',
                        'type' => CustomFieldTypes::DATETIME,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
