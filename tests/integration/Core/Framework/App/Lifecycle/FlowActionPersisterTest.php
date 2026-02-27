<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowActionPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class FlowActionPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private FlowActionPersister $persister;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->persister = static::getContainer()->get(FlowActionPersister::class);
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testPersistAddsNewFlowActions(): void
    {
        $appId = $this->createApp();
        $app = $this->getApp($appId);

        $context = $this->buildContext($app, __DIR__ . '/_fixtures/withFlowExtension');

        $this->persister->persist($context);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $flowActions);
        static::assertSame('telegram.send.message', $flowActions[0]['name']);
    }

    public function testPersistUpdatesExistingFlowActions(): void
    {
        $appId = $this->createApp();
        $app = $this->getApp($appId);

        $context = $this->buildContext($app, __DIR__ . '/_fixtures/withFlowExtension');
        $this->persister->persist($context);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $flowActions);

        $contextV2 = $this->buildContext($app, __DIR__ . '/_fixtures/withFlowExtensionV2');
        $this->persister->persist($contextV2);

        $newFlowActions = $this->getAppFlowActions($appId);
        static::assertCount(2, $newFlowActions);

        foreach ($flowActions as $action) {
            static::assertContains($action['id'], array_column($newFlowActions, 'id'));
        }
    }

    public function testPersistDeletesRemovedFlowActions(): void
    {
        $appId = $this->createApp();
        $app = $this->getApp($appId);

        $context = $this->buildContext($app, __DIR__ . '/_fixtures/withFlowExtension');
        $this->persister->persist($context);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $flowActions);

        $contextV3 = $this->buildContext($app, __DIR__ . '/_fixtures/withFlowExtensionV3');
        $this->persister->persist($contextV3);

        $newFlowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $newFlowActions);

        foreach ($flowActions as $action) {
            static::assertNotContains($action['id'], array_column($newFlowActions, 'id'));
        }
    }

    public function testPersistPreservesFlowActionUsedInFlowBuilder(): void
    {
        $appId = $this->createApp();
        $app = $this->getApp($appId);

        $context = $this->buildContext($app, __DIR__ . '/_fixtures/withFlowExtension');
        $this->persister->persist($context);

        $flowActions = $this->getAppFlowActions($appId);
        static::assertCount(1, $flowActions);

        $flowId = Uuid::randomHex();
        $this->createFlow($flowId);

        $sequenceId = Uuid::randomHex();
        $this->createSequence($sequenceId, $flowId, $flowActions[0]['id']);

        $contextV2 = $this->buildContext($app, __DIR__ . '/_fixtures/withFlowExtensionV2');
        $this->persister->persist($contextV2);

        $appFlowActionId = $this->getAppFlowActionIdFromSequence($sequenceId);
        static::assertSame($flowActions[0]['id'], $appFlowActionId);
    }

    private function buildContext(AppEntity $app, string $appDir): AppLifecycleContext
    {
        $manifest = $this->createMock(Manifest::class);

        return new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new Filesystem($appDir),
            defaultLocale: 'en-GB',
            isInstall: false,
        );
    }

    private function createApp(): string
    {
        $id = Uuid::randomHex();
        $app = [
            'id' => $id,
            'name' => 'FlowActionTestApp',
            'active' => true,
            'path' => __DIR__ . '/_fixtures/withFlowExtension',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'FlowActionTestApp',
            ],
        ];

        static::getContainer()->get('app.repository')->create([$app], Context::createDefaultContext());

        return $id;
    }

    private function getApp(string $appId): AppEntity
    {
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');
        $app = $appRepository->search(new Criteria([$appId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(AppEntity::class, $app);

        return $app;
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
