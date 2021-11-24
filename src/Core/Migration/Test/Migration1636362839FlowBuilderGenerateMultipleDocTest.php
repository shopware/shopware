<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1636362839FlowBuilderGenerateMultipleDoc;

class Migration1636362839FlowBuilderGenerateMultipleDocTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Migration1636362839FlowBuilderGenerateMultipleDoc $migration;

    private ?EntityRepositoryInterface $flowRepository;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->flowRepository = $this->getContainer()->get('flow.repository');
        $this->migration = new Migration1636362839FlowBuilderGenerateMultipleDoc();
        $this->ids = new TestDataCollection(Context::createDefaultContext());
    }

    public function testMigration(): void
    {
        $this->createFlow();
        $this->migration->update($this->connection);

        $actionGenerateDocs = $this->connection->fetchAssociative(
            'SELECT id, action_name, config FROM flow_sequence WHERE action_name = :actionName',
            [
                'actionName' => 'action.generate.document',
            ]
        );

        $newConfig = json_decode($actionGenerateDocs['config'], true);
        static::assertIsArray($newConfig);
        static::assertNotNull($newConfig['documentTypes']);
    }

    private function createFlow(): void
    {
        $sequenceId = Uuid::randomHex();

        $this->flowRepository->create(array_merge([[
            'name' => 'Create Order',
            'eventName' => TestFlowBusinessEvent::EVENT_NAME,
            'priority' => 10,
            'active' => true,
            'sequences' => array_merge([
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => $this->ids->create('ruleId'),
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                    'rule' => [
                        'id' => $this->ids->create('ruleId'),
                        'name' => 'Test rule',
                        'priority' => 1,
                        'conditions' => [
                            ['type' => (new AlwaysValidRule())->getName()],
                        ],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'action.generate.document',
                    'config' => [
                        'documentType' => 'Invoice',
                        'documentRangerType' => 'document_invoice',
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
            ]),
        ],
        ]), Context::createDefaultContext());
    }
}
