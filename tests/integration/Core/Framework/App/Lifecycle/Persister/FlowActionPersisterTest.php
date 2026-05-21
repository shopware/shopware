<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowActionPersister;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
class FlowActionPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const APP_DIR = __DIR__ . '/_fixtures/withFlowExtension';

    private const APP_DIR_V2 = __DIR__ . '/_fixtures/withFlowExtensionV2';

    private const APP_DIR_V3 = __DIR__ . '/_fixtures/withFlowExtensionV3';

    private FlowActionPersister $persister;

    private Connection $connection;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $this->persister = static::getContainer()->get(FlowActionPersister::class);
        $this->connection = static::getContainer()->get(Connection::class);

        /** @var AppFixture $appFixture */
        $appFixture = static::getContainer()->get(AppFixture::class);
        $this->appFixture = $appFixture;
    }

    public function testPersistAddsNewFlowActions(): void
    {
        $manifest = ManifestFixture::empty();
        $app = $this->appFixture->createApp($manifest);
        $appId = $app->getId();

        $context = $this->appFixture->createInstallContext($app, $manifest, new Filesystem(self::APP_DIR));

        $this->persister->persist($context);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $flowActions);
        static::assertSame('telegram.send.message', $flowActions[0]['name']);
    }

    public function testPersistPreservesExistingFlowActionsOnAppUpdate(): void
    {
        $manifest = ManifestFixture::empty();
        $app = $this->appFixture->createApp($manifest);
        $context = $this->appFixture->createInstallContext($app, $manifest, new Filesystem(self::APP_DIR));
        $this->persister->persist($context);

        $appFlowActions = $this->getAppFlowActions($app->getId());
        static::assertIsArray($appFlowActions);
        static::assertArrayHasKey(0, $appFlowActions);

        $updateContext = $this->appFixture->createUpdateContext($app, $manifest, new Filesystem(self::APP_DIR));
        $this->persister->persist($updateContext);

        $newAppFlowActions = $this->getAppFlowActions($app->getId());
        static::assertIsArray($newAppFlowActions);
        static::assertArrayHasKey(0, $newAppFlowActions);

        static::assertSame($appFlowActions[0], $newAppFlowActions[0]);
    }

    public function testPersistUpdatesExistingFlowActions(): void
    {
        $manifest = ManifestFixture::empty();
        $app = $this->appFixture->createApp($manifest);
        $appId = $app->getId();

        $context = $this->appFixture->createInstallContext($app, $manifest, new Filesystem(self::APP_DIR));
        $this->persister->persist($context);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $flowActions);

        $contextV2 = $this->appFixture->createUpdateContext($app, $manifest, new Filesystem(self::APP_DIR_V2));
        $this->persister->persist($contextV2);

        $newFlowActions = $this->getAppFlowActions($appId);
        static::assertCount(2, $newFlowActions);

        foreach ($flowActions as $action) {
            static::assertContains($action['id'], array_column($newFlowActions, 'id'));
        }
    }

    public function testPersistDeletesRemovedFlowActions(): void
    {
        $manifest = ManifestFixture::empty();
        $app = $this->appFixture->createApp($manifest);
        $appId = $app->getId();

        $context = $this->appFixture->createInstallContext($app, $manifest, new Filesystem(self::APP_DIR));
        $this->persister->persist($context);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $flowActions);

        $contextV3 = $this->appFixture->createUpdateContext($app, $manifest, new Filesystem(self::APP_DIR_V3));
        $this->persister->persist($contextV3);

        $newFlowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $newFlowActions);

        foreach ($flowActions as $action) {
            static::assertNotContains($action['id'], array_column($newFlowActions, 'id'));
        }
    }

    public function testPersistPreservesFlowActionUsedInFlowBuilder(): void
    {
        $manifest = ManifestFixture::empty();
        $app = $this->appFixture->createApp($manifest);
        $appId = $app->getId();

        $context = $this->appFixture->createInstallContext($app, $manifest, new Filesystem(self::APP_DIR));
        $this->persister->persist($context);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $flowActions);

        $flowId = Uuid::randomHex();
        $this->createFlow($flowId);

        $sequenceId = Uuid::randomHex();
        $this->createSequence($sequenceId, $flowId, $flowActions[0]['id']);

        $contextV2 = $this->appFixture->createUpdateContext($app, $manifest, new Filesystem(self::APP_DIR_V2));
        $this->persister->persist($contextV2);

        $appFlowActionId = $this->getAppFlowActionIdFromSequence($sequenceId);
        static::assertSame($flowActions[0]['id'], $appFlowActionId);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function getAppFlowActions(string $appId): array
    {
        /** @var list<array{id: string, name: string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) AS id, name FROM app_flow_action WHERE app_id = :appId',
            ['appId' => Uuid::fromHexToBytes($appId)]
        );
    }

    private function createFlow(string $flowId): void
    {
        $this->connection->insert('flow', [
            'id' => Uuid::fromHexToBytes($flowId),
            'name' => 'Test Flow',
            'event_name' => 'checkout.order.placed',
            'priority' => 1,
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createSequence(string $sequenceId, string $flowId, string $appFlowActionId): void
    {
        $this->connection->insert('flow_sequence', [
            'id' => Uuid::fromHexToBytes($sequenceId),
            'flow_id' => Uuid::fromHexToBytes($flowId),
            'app_flow_action_id' => Uuid::fromHexToBytes($appFlowActionId),
            'action_name' => 'app.telegram.send.message',
            'position' => 1,
            'display_group' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getAppFlowActionIdFromSequence(string $sequenceId): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT LOWER(HEX(app_flow_action_id)) FROM flow_sequence WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($sequenceId)]
        );

        return $result ?: null;
    }
}
