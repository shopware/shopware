<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Health;

use Doctrine\DBAL\Connection;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Verifies that health warnings contain only the documented scalar context.
 *
 * @internal
 */
class WebhookHealthLogRedactionTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SECRET = 'tok_SuperSecret_DEADBEEF';
    private const URL = 'https://hooks.example.com/incoming?token=' . self::SECRET;
    private const NON_TRANSIENT_THRESHOLD = 3;
    private const MAX_SUSPENDED_DAYS = 7;

    private IdsCollection $ids;

    private Connection $connection;

    private MockClock $clock;

    private EventDispatcher $dispatcher;

    private TestHandler $logHandler;

    private WebhookHealthService $service;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-06-15 12:00:00.000'));
        $this->dispatcher = new EventDispatcher();
        $this->logHandler = new TestHandler();

        $this->service = new WebhookHealthService(
            $this->connection,
            new WebhookOutboxStore($this->connection, $this->clock),
            new HealthConfig([300, 600, 1200, 2400, 3600, 14400], 5, self::NON_TRANSIENT_THRESHOLD, self::MAX_SUSPENDED_DAYS),
            $this->clock,
            $this->dispatcher,
            new Logger('test', [$this->logHandler]),
        );
    }

    public function testOperatorDisableWarningCarriesOnlyTheWebhookIdAndNeverTheEndpointSecret(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy);

        $this->service->disableByOperator($this->ids->get('wh'));

        $warning = $this->singleWarning('Webhook endpoint disabled by operator');
        static::assertSame(['webhookId' => $this->ids->get('wh')], $warning->context);
        $this->assertNoSecretAnywhere();
    }

    public function testEscalationDisableWarningCarriesOnlyScalarsAndNeverTheEndpointSecret(): void
    {
        $this->seedWebhook('wh', active: false, errorCount: self::NON_TRANSIENT_THRESHOLD);
        $this->seedHealth(
            'wh',
            EndpointState::Suspended,
            cnf: self::NON_TRANSIENT_THRESHOLD,
            cooldownUntil: $this->clock->now()->modify('+1 hour')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            suspendedSince: $this->clock->now()->modify('-8 days')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        );

        $this->service->tick();

        $warning = $this->singleWarning('Webhook endpoint disabled after exceeding the suspension bound');
        static::assertSame(
            ['webhookId' => $this->ids->get('wh'), 'maxSuspendedDays' => self::MAX_SUSPENDED_DAYS],
            $warning->context,
        );
        $this->assertNoSecretAnywhere();
    }

    public function testBestEffortListenerFailureWarningOmitsTheListenerExceptionMessage(): void
    {
        $this->seedWebhook('wh', active: true, errorCount: 0);
        $this->seedHealth('wh', EndpointState::Healthy, cnf: self::NON_TRANSIENT_THRESHOLD - 1);
        $this->dispatcher->addListener(WebhookSuspendedEvent::class, static function (): void {
            throw new \RuntimeException('listener leaked ' . self::SECRET);
        });

        $this->service->recordFailure($this->ids->get('wh'), ErrorClassification::NonTransientAuth, 1);

        $warning = $this->singleWarning('Webhook lifecycle event listener failed');
        static::assertSame(
            ['event' => WebhookSuspendedEvent::class, 'exception' => \RuntimeException::class],
            $warning->context,
        );
        $this->assertNoSecretAnywhere();
    }

    private function assertNoSecretAnywhere(): void
    {
        $haystack = '';
        foreach ($this->logHandler->getRecords() as $record) {
            $haystack .= $record->message . '|' . json_encode($record->context, \JSON_THROW_ON_ERROR);
        }

        static::assertStringNotContainsString(self::SECRET, $haystack);
        static::assertStringNotContainsString('hooks.example.com', $haystack);
    }

    private function singleWarning(string $message): LogRecord
    {
        $matching = array_values(array_filter(
            $this->logHandler->getRecords(),
            static fn (LogRecord $record): bool => $record->message === $message
        ));

        static::assertCount(1, $matching);

        return $matching[0];
    }

    private function seedWebhook(string $key, bool $active, int $errorCount): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function seedHealth(
        string $key,
        EndpointState $state,
        int $cnf = 0,
        ?string $cooldownUntil = null,
        ?string $suspendedSince = null,
    ): void {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'consecutive_transient_failures' => 0,
            'consecutive_non_transient_failures' => $cnf,
            'degraded_cycle_count' => 0,
            'cooldown_until' => $cooldownUntil,
            'suspended_since' => $suspendedSince,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
