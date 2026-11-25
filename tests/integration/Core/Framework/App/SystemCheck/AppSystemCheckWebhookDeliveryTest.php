<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\SystemCheck;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\SystemCheck\AppSystemCheck;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * Drives the real webhook producers with a mocked app server and reads the resulting state through AppSystemCheck.
 *
 * @internal
 */
#[Package('framework')]
class AppSystemCheckWebhookDeliveryTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private string $appId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->appId = $this->createAppWithWebhook();
    }

    public function testRejectedDeliveryUnderWebhooksReworkIsCredentialsRejected(): void
    {
        $this->appendNewResponse(new Response(401));

        $this->dispatchAndDrain(reworkActive: true, adminWorker: false);

        $health = $this->currentHealth();
        static::assertSame('ERROR', $health['status']);
        static::assertSame(AppSystemCheck::REASON_CREDENTIALS_REJECTED, $health['reason']);
        static::assertSame(401, $health['lastStatusCode']);
    }

    public function testServerErrorUnderWebhooksReworkIsDeliveryFailing(): void
    {
        $this->appendNewResponse(new Response(500));

        $this->dispatchAndDrain(reworkActive: true, adminWorker: false);

        $health = $this->currentHealth();
        static::assertSame('WARNING', $health['status']);
        static::assertSame(AppSystemCheck::REASON_DELIVERY_FAILING, $health['reason']);
        static::assertSame(500, $health['lastStatusCode']);
    }

    public function testSuccessfulDeliveryUnderWebhooksReworkIsOk(): void
    {
        $this->appendNewResponse(new Response(200));

        $this->dispatchAndDrain(reworkActive: true, adminWorker: false);

        $health = $this->currentHealth();
        static::assertSame('OK', $health['status']);
        static::assertNull($health['reason']);
    }

    /**
     * Known gap: the legacy sync path (admin worker on, WEBHOOKS_REWORK off) records no failure signal,
     * so a rejected delivery is invisible to the check until the webhook health rework covers it.
     */
    public function testKnownGapLegacySyncPathRejectedDeliveryStaysOk(): void
    {
        $this->appendNewResponse(new Response(401));

        $this->dispatchAndDrain(reworkActive: false, adminWorker: true);

        static::assertSame(1, $this->getRequestCount());
        $health = $this->currentHealth();
        static::assertSame('OK', $health['status']);
        static::assertNull($health['reason']);
    }

    private function dispatchAndDrain(bool $reworkActive, bool $adminWorker): void
    {
        $manager = $this->getWebhookManager($adminWorker);
        $event = new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $run = function () use ($manager, $event): void {
            $manager->dispatch($event);
            $this->runWorker();
        };

        $reworkActive
            ? Feature::withFeatureEnabled('WEBHOOKS_REWORK', $run)
            : Feature::withFeatureDisabled('WEBHOOKS_REWORK', $run);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentHealth(): array
    {
        $check = new AppSystemCheck(
            $this->connection,
            static::getContainer()->get(ClockInterface::class),
        );

        $name = $this->connection->fetchOne('SELECT name FROM app WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->appId)]);
        $apps = array_column($check->run()->extra['apps'], null, 'name');
        static::assertArrayHasKey($name, $apps);

        return $apps[$name];
    }

    private function getWebhookManager(bool $isAdminWorkerEnabled): WebhookManager
    {
        $webhookClient = new WebhookClient(
            static::getContainer()->get('shopware.webhook.guzzle'),
            static::getContainer()->get(ClockInterface::class),
        );

        $deliveryService = new WebhookDeliveryService(
            $webhookClient,
            static::getContainer()->get(AppPayloadServiceHelper::class),
            static::getContainer()->get(WebhookSigningSecretResolver::class),
            static::getContainer()->get(WebhookOutboxStore::class),
            static::getContainer()->get(RetryDelayCalculator::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(WebhookHealthService::class),
            static::getContainer()->get('logger'),
            $isAdminWorkerEnabled,
        );

        return new WebhookManager(
            static::getContainer()->get(WebhookLoader::class),
            static::getContainer()->get(HookableEventFactory::class),
            static::getContainer()->get(AppLocaleProvider::class),
            static::getContainer()->get(AppPayloadServiceHelper::class),
            $webhookClient,
            static::getContainer()->get('messenger.default_bus'),
            $_SERVER['APP_URL'],
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $isAdminWorkerEnabled,
            $deliveryService,
            static::getContainer()->get(WebhookOutboxStore::class),
            static::getContainer()->get(PolicyRegistry::class),
        );
    }

    private function createAppWithWebhook(): string
    {
        $appId = (new AppFixture(static::getContainer()->get('app.repository')))
            ->createAppFromData(['appSecret' => 'app-secret'])
            ->getId();

        static::getContainer()->get('webhook.repository')->create([[
            'name' => 'webhook-' . $appId,
            'eventName' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://example.com/webhook',
            'appId' => $appId,
        ]], Context::createDefaultContext());

        return $appId;
    }
}
