<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Flow\Event\Event;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\FlowEventLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
class FlowEventLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const APP_DIR = __DIR__ . '/_fixtures/withFlowExtension';

    private FlowEventLifecycleHandler $persister;

    private Connection $connection;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $this->persister = static::getContainer()->get(FlowEventLifecycleHandler::class);
        $this->connection = static::getContainer()->get(Connection::class);

        /** @var AppFixture $appFixture */
        $appFixture = static::getContainer()->get(AppFixture::class);
        $this->appFixture = $appFixture;
    }

    public function testPersistPreservesExistingFlowEventsOnAppUpdate(): void
    {
        $manifest = ManifestFixture::empty();
        $app = $this->appFixture->createApp($manifest);
        $context = new AppPersistContext($manifest, $app, Context::createDefaultContext(), new Filesystem(self::APP_DIR), 'en-GB');
        $this->persister->install($context);

        $appFlowEvents = $this->getAppFlowEvents($app->getId());
        static::assertIsArray($appFlowEvents);
        static::assertArrayHasKey(0, $appFlowEvents);

        $updateContext = new AppPersistContext($manifest, $app, Context::createDefaultContext(), new Filesystem(self::APP_DIR), 'en-GB');
        $this->persister->update($updateContext);

        $newAppEvents = $this->getAppFlowEvents($app->getId());
        static::assertIsArray($newAppEvents);
        static::assertArrayHasKey(0, $newAppEvents);

        static::assertSame($appFlowEvents[0], $newAppEvents[0]);
    }

    #[DataProvider('refreshFlowEventsProvider')]
    public function testRefreshFlowEvents(string $flowEventPath, int $expectedCount): void
    {
        $context = Context::createDefaultContext();
        $app = $this->createAppWithFlowEvents();
        $appId = $app->getId();

        $flowEvents = $this->getAppFlowEvents($appId);
        static::assertIsArray($flowEvents);

        $flowEvent = Event::createFromXmlFile($flowEventPath);
        $this->persister->updateEvents($flowEvent, $appId, $context, 'en-GB');

        $newFlowEvents = $this->getAppFlowEvents($appId);
        static::assertIsArray($newFlowEvents);
        static::assertCount($expectedCount, $newFlowEvents);
        foreach ($flowEvents as $event) {
            static::assertContains($event['id'], array_column($newFlowEvents, 'id'));
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function refreshFlowEventsProvider(): iterable
    {
        yield 'additional event' => [self::APP_DIR . '/Resources/flow-v2.xml', 2];
        yield 'another event' => [self::APP_DIR . '/Resources/flow-v3.xml', 1];
    }

    public function testRefreshFlowEventUsedInFlowBuilder(): void
    {
        $context = Context::createDefaultContext();
        $app = $this->createAppWithFlowEvents();
        $appId = $app->getId();

        $flowEvents = $this->getAppFlowEvents($appId);
        static::assertIsArray($flowEvents);
        static::assertArrayHasKey(0, $flowEvents);
        static::assertIsArray($flowEvents[0]);
        static::assertArrayHasKey('id', $flowEvents[0]);

        $flowId = Uuid::randomHex();
        $this->createFlow($flowId, 'checkout.order.place.custom', $flowEvents[0]['id']);

        $sequenceId = Uuid::randomHex();
        $this->createSequence($sequenceId, $flowId);

        $flow = $this->getAppFlowEventFromFlow($flowEvents[0]['id']);
        static::assertNotNull($flow);

        $flowEvent = Event::createFromXmlFile(self::APP_DIR . '/Resources/flow-v2.xml');
        $this->persister->updateEvents($flowEvent, $appId, $context, 'en-GB');

        $flow = $this->getAppFlowEventFromFlow($flowEvents[0]['id']);
        static::assertNotNull($flow);
    }

    private function createAppWithFlowEvents(): AppEntity
    {
        $manifest = ManifestFixture::empty();
        $app = $this->appFixture->createApp($manifest);
        $context = new AppPersistContext($manifest, $app, Context::createDefaultContext(), new Filesystem(self::APP_DIR), 'en-GB');

        $this->persister->install($context);

        return $app;
    }

    private function getAppFlowEventFromFlow(string $appFlowEventId): ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('event_name');
        $query->from('flow');
        $query->where('app_flow_event_id = :appFlowEventId');
        $query->setParameter('appFlowEventId', Uuid::fromHexToBytes($appFlowEventId));

        return $query->executeQuery()->fetchOne() ?: null;
    }

    private function createFlow(string $flowId, string $eventName = 'checkout.order.placed', ?string $appEventId = null): void
    {
        $this->connection->insert('flow', [
            'id' => Uuid::fromHexToBytes($flowId),
            'app_flow_event_id' => $appEventId ? Uuid::fromHexToBytes($appEventId) : null,
            'name' => 'Test Flow',
            'event_name' => $eventName,
            'priority' => 1,
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createSequence(string $sequenceId, string $flowId, ?string $appFlowActionId = null): void
    {
        $this->connection->insert('flow_sequence', [
            'id' => Uuid::fromHexToBytes($sequenceId),
            'flow_id' => Uuid::fromHexToBytes($flowId),
            'app_flow_action_id' => $appFlowActionId ? Uuid::fromHexToBytes($appFlowActionId) : null,
            'action_name' => 'app.telegram.send.message',
            'position' => 1,
            'display_group' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return array<int, mixed>|null
     */
    private function getAppFlowEvents(string $appId): ?array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('lower(hex(id)) AS id');
        $query->from('app_flow_event');
        $query->where('app_id = :appId');
        $query->setParameter('appId', Uuid::fromHexToBytes($appId));

        return $query->executeQuery()->fetchAllAssociative() ?: null;
    }
}
