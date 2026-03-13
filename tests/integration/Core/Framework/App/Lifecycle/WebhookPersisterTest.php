<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhook;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhooks;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
class WebhookPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WebhookPersister $persister;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->persister = static::getContainer()->get(WebhookPersister::class);
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testPersistWebhooks(): void
    {
        $appId = $this->createApp('App1');

        $context = $this->buildContext($appId, $this->createManifest([
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'https://example.com/event/rule-changed', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        ]));

        $this->persister->persist($context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(3, $fromDb);
        static::assertSame(
            ['1', '1', '1'],
            array_column($fromDb, 'active')
        );
        static::assertSame(
            ['0', '1', '0'],
            array_column($fromDb, 'only_live_version')
        );
    }

    public function testUpdates(): void
    {
        $appId = $this->createApp('App1');

        $context = $this->buildContext($appId, $this->createManifest([
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'https://example.com/event/rule-changed', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        ]));

        $this->persister->persist($context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(3, $fromDb);

        $contextUpdate = $this->buildContext($appId, $this->createManifest([
            Webhook::fromArray(['name' => 'hook1', 'url' => 'new-url', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'new-url-2', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'new-url-3', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        ]));

        $this->persister->persist($contextUpdate);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(3, $fromDb);
        static::assertSame(
            ['hook1', 'hook2', 'hook3'],
            array_column($fromDb, 'name')
        );
        static::assertSame(
            ['new-url', 'new-url-2', 'new-url-3'],
            array_column($fromDb, 'url')
        );
    }

    public function testOldWebhooksAreDeleted(): void
    {
        $appId = $this->createApp('App1');

        $context = $this->buildContext($appId, $this->createManifest([
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'https://example.com/event/rule-changed', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        ]));

        $this->persister->persist($context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(3, $fromDb);

        $contextReduced = $this->buildContext($appId, $this->createManifest([
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
        ]));

        $this->persister->persist($contextReduced);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(2, $fromDb);
        static::assertSame(['hook1', 'hook2'], array_column($fromDb, 'name'));
    }

    public function testPersistClearsManagerCache(): void
    {
        $appId = $this->createApp('App1');

        $webhookManager = static::getContainer()->get(WebhookManager::class);

        // save first set of 2 webhooks
        $context = $this->buildContext($appId, $this->createManifest([
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
        ]));

        $this->persister->persist($context);

        // trigger loading of webhooks
        $webhookManager->dispatch(new AppDeletedEvent('app-id', Context::createDefaultContext()));
        $webhookCache = (new \ReflectionProperty(WebhookManager::class, 'webhooks'))->getValue($webhookManager);

        static::assertCount(2, $webhookCache);

        // update webhooks with existing + new hook
        $contextFull = $this->buildContext($appId, $this->createManifest([
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'https://example.com/event/rule-changed', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        ]));

        $this->persister->persist($contextFull);

        // trigger loading of webhooks
        $webhookManager->dispatch(new AppDeletedEvent('app-id', Context::createDefaultContext()));
        $webhookCache = (new \ReflectionProperty(WebhookManager::class, 'webhooks'))->getValue($webhookManager);

        // should now be three
        static::assertCount(3, $webhookCache);
    }

    /**
     * @param array<Webhook> $webhooks
     */
    private function createManifest(array $webhooks): Manifest
    {
        $manifest = $this->createMock(Manifest::class);
        $manifest->method('getWebhooks')->willReturn(Webhooks::fromArray(['webhooks' => $webhooks]));

        return $manifest;
    }

    private function buildContext(string $appId, Manifest $manifest): AppLifecycleContext
    {
        $app = $this->getApp($appId);

        return new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
            isInstall: true,
        );
    }

    private function createApp(string $name): string
    {
        $id = Uuid::randomHex();
        $app = [
            'id' => $id,
            'name' => $name,
            'active' => true,
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
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
                'name' => $name,
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
}
