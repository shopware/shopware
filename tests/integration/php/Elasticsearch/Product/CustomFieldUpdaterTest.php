<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 * @group skip-paratest
 */
class CustomFieldUpdaterTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    private Client $client;

    private ElasticsearchOutdatedIndexDetector $indexDetector;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = $this->getContainer()->get(Client::class);
        $this->indexDetector = $this->getContainer()->get(ElasticsearchOutdatedIndexDetector::class);
    }

    public function testCreateIndices(): void
    {
        $this->clearElasticsearch();

        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeStatement('DELETE FROM custom_field');

        $this->getContainer()
            ->get(ElasticsearchIndexingCommand::class)
            ->run(new ArrayInput([]), new NullOutput());

        static::assertNotEmpty($this->indexDetector->getAllUsedIndices());
    }

    /**
     * @depends testCreateIndices
     */
    public function testCreateCustomFields(): void
    {
        $customFieldRepository = $this->getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
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
        $properties = $indices['mappings']['properties']['customFields']['properties'];

        static::assertArrayHasKey('test_newly_created_field', $properties);
        static::assertSame('long', $properties['test_newly_created_field']['type']);

        static::assertArrayHasKey('test_newly_created_field_text', $properties);
        static::assertSame('text', $properties['test_newly_created_field_text']['type']);
    }

    /**
     * @depends testCreateCustomFields
     */
    public function testRelationWillBeSetLaterOn(): void
    {
        $customFieldRepository = $this->getContainer()->get('custom_field_set.repository');

        $id = Uuid::randomHex();

        $customFieldRepository->create([
            [
                'id' => $id,
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
                'id' => $id,
                'relations' => [[
                    'entityName' => 'product',
                ]],
            ],
        ], Context::createDefaultContext());

        $indexName = array_keys($this->indexDetector->getAllUsedIndices())[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'];

        static::assertArrayHasKey('test_later_created_field', $properties);
        static::assertSame('long', $properties['test_later_created_field']['type']);

        static::assertArrayHasKey('test_later_created_field_text', $properties);
        static::assertSame('text', $properties['test_later_created_field_text']['type']);

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
