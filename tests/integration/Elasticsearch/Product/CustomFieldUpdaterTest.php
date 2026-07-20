<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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
class CustomFieldUpdaterTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    private Client $client;

    private ElasticsearchOutdatedIndexDetector $indexDetector;

    private IdsCollection $ids;

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

        if (!self::$indexReady) {
            $this->createIndex();
            self::$indexReady = true;
        }
    }

    protected function tearDown(): void
    {
        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->delete([
            ['id' => $this->ids->get('custom-field-set-1')],
        ], Context::createDefaultContext());
    }

    public function testCreateIndices(): void
    {
        static::assertNotEmpty($this->indexDetector->getAllUsedIndices());
    }

    public function testCreateCustomFields(): void
    {
        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('custom-field-set-1'),
                'name' => 'swag_example_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'English custom field set label',
                        'de-DE' => 'German custom field set label',
                    ],
                ],
                'relations' => [[
                    'entityName' => 'product',
                ]],
                'customFields' => [
                    [
                        'name' => 'test_newly_created_field',
                        'type' => CustomFieldTypes::INT,
                        'includeInSearch' => true,
                    ],
                    [
                        'name' => 'test_newly_created_field_text',
                        'type' => CustomFieldTypes::TEXT,
                        'includeInSearch' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $indexName = array_keys($this->indexDetector->getAllUsedIndices())[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'] ?? [];

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $properties);
        $properties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'];
        static::assertIsArray($properties);
        static::assertArrayHasKey('test_newly_created_field', $properties);
        static::assertSame('long', $properties['test_newly_created_field']['type']);

        static::assertArrayHasKey('test_newly_created_field_text', $properties);
        static::assertSame('keyword', $properties['test_newly_created_field_text']['type']);
    }

    public function testRelationWillBeSetLaterOn(): void
    {
        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('custom-field-set-1'),
                'name' => 'swag_example_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'English custom field set label',
                        'de-DE' => 'German custom field set label',
                    ],
                ],
                'customFields' => [
                    [
                        'name' => 'test_later_created_field',
                        'type' => CustomFieldTypes::INT,
                        'includeInSearch' => true,
                    ],
                    [
                        'name' => 'test_later_created_field_text',
                        'type' => CustomFieldTypes::TEXT,
                        'includeInSearch' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $customFieldRepository->update([
            [
                'id' => $this->ids->get('custom-field-set-1'),
                'relations' => [[
                    'entityName' => 'product',
                ]],
            ],
        ], Context::createDefaultContext());

        $indexName = array_keys($this->indexDetector->getAllUsedIndices())[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'];

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $properties);
        $properties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'];
        static::assertIsArray($properties);

        static::assertArrayHasKey('test_later_created_field', $properties);
        static::assertSame('long', $properties['test_later_created_field']['type']);

        static::assertArrayHasKey('test_later_created_field_text', $properties);
        static::assertSame('keyword', $properties['test_later_created_field_text']['type']);
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

        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('DELETE FROM custom_field');

        $command = new ElasticsearchIndexingCommand(
            static::getContainer()->get(ElasticsearchIndexer::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(CreateAliasTaskHandler::class),
            true
        );

        $command->run(new ArrayInput([]), new NullOutput());
    }
}
