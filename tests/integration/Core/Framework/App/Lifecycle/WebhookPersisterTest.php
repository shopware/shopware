<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

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

    public function testUpdateWebhooksFromArray(): void
    {
        $appId = $this->createApp('App1');

        $webhooks = [
            [
                'name' => 'hook1',
                'eventName' => 'product.written',
                'url' => 'https://example.com/event/product-changed',
            ],
            [
                'name' => 'hook2',
                'eventName' => 'category.written',
                'url' => 'https://example.com/event/category-changed',
                'onlyLiveVersion' => true,
            ],
            [
                'name' => 'hook3',
                'eventName' => 'rule.written',
                'url' => 'https://example.com/event/rule-changed',
                'onlyLiveVersion' => false,
                'active' => false,
            ],
        ];
        $context = Context::createDefaultContext();

        $this->persister->updateWebhooksFromArray($webhooks, $appId, $context);

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

        $webhooks = [
            [
                'name' => 'hook1',
                'eventName' => 'product.written',
                'url' => 'https://example.com/event/product-changed',
            ],
            [
                'name' => 'hook2',
                'eventName' => 'category.written',
                'url' => 'https://example.com/event/category-changed',
                'onlyLiveVersion' => true,
            ],
            [
                'name' => 'hook3',
                'eventName' => 'rule.written',
                'url' => 'https://example.com/event/rule-changed',
                'onlyLiveVersion' => false,
                'active' => true,
            ],
        ];
        $context = Context::createDefaultContext();

        $this->persister->updateWebhooksFromArray($webhooks, $appId, $context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(3, $fromDb);

        $webhooks = [
            [
                'name' => 'hook1',
                'eventName' => 'product.written',
                'url' => 'new-url',
            ],
            [
                'name' => 'hook2',
                'eventName' => 'category.written',
                'url' => 'new-url-2',
                'onlyLiveVersion' => true,
            ],
            [
                'name' => 'hook3',
                'eventName' => 'rule.written',
                'url' => 'new-url-3',
                'onlyLiveVersion' => false,
                'active' => true,
            ],
        ];

        $this->persister->updateWebhooksFromArray($webhooks, $appId, $context);

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

        $webhooks = [
            [
                'name' => 'hook1',
                'eventName' => 'product.written',
                'url' => 'https://example.com/event/product-changed',
            ],
            [
                'name' => 'hook2',
                'eventName' => 'category.written',
                'url' => 'https://example.com/event/category-changed',
                'onlyLiveVersion' => true,
            ],
            [
                'name' => 'hook3',
                'eventName' => 'rule.written',
                'url' => 'https://example.com/event/rule-changed',
                'onlyLiveVersion' => false,
                'active' => true,
            ],
        ];
        $context = Context::createDefaultContext();

        $this->persister->updateWebhooksFromArray($webhooks, $appId, $context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(3, $fromDb);

        $webhooks = [
            [
                'name' => 'hook1',
                'eventName' => 'product.written',
                'url' => 'https://example.com/event/product-changed',
            ],
            [
                'name' => 'hook2',
                'eventName' => 'category.written',
                'url' => 'https://example.com/event/category-changed',
                'onlyLiveVersion' => true,
            ],
        ];

        $this->persister->updateWebhooksFromArray($webhooks, $appId, $context);

        $fromDb = $this->connection->fetchAllAssociative('SELECT * FROM webhook');

        static::assertCount(2, $fromDb);
        static::assertSame(['hook1', 'hook2'], array_column($fromDb, 'name'));
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
}
