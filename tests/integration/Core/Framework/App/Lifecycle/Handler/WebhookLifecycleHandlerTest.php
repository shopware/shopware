<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\WebhookLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhook;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
class WebhookLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WebhookLifecycleHandler $handler;

    private Connection $connection;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $this->handler = static::getContainer()->get(WebhookLifecycleHandler::class);
        $this->connection = static::getContainer()->get(Connection::class);
        /** @var AppFixture $appFixture */
        $appFixture = static::getContainer()->get(AppFixture::class);
        $this->appFixture = $appFixture;
    }

    public function testPersistWebhooks(): void
    {
        $manifest = ManifestFixture::empty()->withWebhooks(
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'https://example.com/event/rule-changed', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        );
        $app = $this->appFixture->createApp($manifest);
        $context = new AppPersistContext($manifest, $app, Context::createDefaultContext(), new StaticFilesystem(), 'en-GB');

        $this->handler->install($context);

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

    public function testDoesNotPersistManifestWebhooksWithoutAppSecret(): void
    {
        $manifest = ManifestFixture::empty()->withWebhooks(
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
        );
        $app = $this->appFixture->createApp($manifest, null);
        $context = new AppPersistContext($manifest, $app, Context::createDefaultContext(), new StaticFilesystem(), 'en-GB');

        $this->handler->install($context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(0, $fromDb);
    }

    public function testUpdates(): void
    {
        $manifest = ManifestFixture::empty()->withWebhooks(
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'https://example.com/event/rule-changed', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        );
        $app = $this->appFixture->createApp($manifest);
        $context = new AppPersistContext($manifest, $app, Context::createDefaultContext(), new StaticFilesystem(), 'en-GB');

        $this->handler->install($context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(3, $fromDb);

        $updatedManifest = ManifestFixture::empty()->withWebhooks(
            Webhook::fromArray(['name' => 'hook1', 'url' => 'new-url', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'new-url-2', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'new-url-3', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        );
        $contextUpdate = new AppPersistContext($updatedManifest, $app, Context::createDefaultContext(), new StaticFilesystem(), 'en-GB');

        $this->handler->update($contextUpdate);

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
        $manifest = ManifestFixture::empty()->withWebhooks(
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'https://example.com/event/rule-changed', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        );
        $app = $this->appFixture->createApp($manifest);
        $context = new AppPersistContext($manifest, $app, Context::createDefaultContext(), new StaticFilesystem(), 'en-GB');

        $this->handler->install($context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(3, $fromDb);

        $reducedManifest = ManifestFixture::empty()->withWebhooks(
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
        );
        $contextReduced = new AppPersistContext($reducedManifest, $app, Context::createDefaultContext(), new StaticFilesystem(), 'en-GB');

        $this->handler->update($contextReduced);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(2, $fromDb);
        static::assertSame(['hook1', 'hook2'], array_column($fromDb, 'name'));
    }

    public function testPersistClearsManagerCache(): void
    {
        $webhookManager = static::getContainer()->get(WebhookManager::class);

        // save first set of 2 webhooks
        $manifest = ManifestFixture::empty()->withWebhooks(
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
        );
        $app = $this->appFixture->createApp($manifest);
        $context = new AppPersistContext($manifest, $app, Context::createDefaultContext(), new StaticFilesystem(), 'en-GB');

        $this->handler->install($context);

        // trigger loading of webhooks
        $webhookManager->dispatch(new AppDeletedEvent('app-id', Context::createDefaultContext()));
        $webhookCache = (new \ReflectionProperty(WebhookManager::class, 'webhooks'))->getValue($webhookManager);

        static::assertCount(2, $webhookCache);

        // update webhooks with existing + new hook
        $fullManifest = ManifestFixture::empty()->withWebhooks(
            Webhook::fromArray(['name' => 'hook1', 'url' => 'https://example.com/event/product-changed', 'event' => 'product.written', 'onlyLiveVersion' => false]),
            Webhook::fromArray(['name' => 'hook2', 'url' => 'https://example.com/event/category-changed', 'event' => 'category.written', 'onlyLiveVersion' => true]),
            Webhook::fromArray(['name' => 'hook3', 'url' => 'https://example.com/event/rule-changed', 'event' => 'rule.written', 'onlyLiveVersion' => false]),
        );
        $contextFull = new AppPersistContext($fullManifest, $app, Context::createDefaultContext(), new StaticFilesystem(), 'en-GB');

        $this->handler->update($contextFull);

        // trigger loading of webhooks
        $webhookManager->dispatch(new AppDeletedEvent('app-id', Context::createDefaultContext()));
        $webhookCache = (new \ReflectionProperty(WebhookManager::class, 'webhooks'))->getValue($webhookManager);

        // should now be three
        static::assertCount(3, $webhookCache);
    }
}
