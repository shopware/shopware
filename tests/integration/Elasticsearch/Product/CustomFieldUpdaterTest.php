<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
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
#[Group('skip-paratest')]
class CustomFieldUpdaterTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    private Client $client;

    private ElasticsearchOutdatedIndexDetector $indexDetector;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();

        $this->client = $this->getContainer()->get(Client::class);
        $this->indexDetector = $this->getContainer()->get(ElasticsearchOutdatedIndexDetector::class);
    }

    protected function tearDown(): void
    {
        $customFieldRepository = $this->getContainer()->get('custom_field_set.repository');

        $customFieldRepository->delete([
            ['id' => $this->ids->get('custom-field-set-1')],
        ], Context::createDefaultContext());
    }

    public function testCreateIndices(): void
    {
        $this->clearElasticsearch();

        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeStatement('DELETE FROM custom_field');

        $command = new ElasticsearchIndexingCommand(
            $this->getContainer()->get(ElasticsearchIndexer::class),
            $this->getContainer()->get('messenger.bus.shopware'),
            $this->getContainer()->get(CreateAliasTaskHandler::class),
            true
        );

        $command->run(new ArrayInput([]), new NullOutput());

        static::assertNotEmpty($this->indexDetector->getAllUsedIndices());
    }

    #[Depends('testCreateIndices')]
    public function testCreateCustomFields(): void
    {
        $customFieldRepository = $this->getContainer()->get('custom_field_set.repository');

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
                    ],
                    [
                        'name' => 'test_newly_created_field_text',
                        'type' => CustomFieldTypes::TEXT,
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

    #[Depends('testCreateCustomFields')]
    public function testRelationWillBeSetLaterOn(): void
    {
        $customFieldRepository = $this->getContainer()->get('custom_field_set.repository');

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
                    ],
                    [
                        'name' => 'test_later_created_field_text',
                        'type' => CustomFieldTypes::TEXT,
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

        $this->clearElasticsearch();
        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM elasticsearch_index_task');
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    protected function runWorker(): void
    {
    }
}
