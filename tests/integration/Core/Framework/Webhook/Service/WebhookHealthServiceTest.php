<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class WebhookHealthServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Connection $connection;

    private WebhookHealthService $service;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->service = static::getContainer()->get(WebhookHealthService::class);
    }

    public function testRecordTerminalFailureIncrementsBelowThreshold(): void
    {
        $this->insertWebhook('wh-1', errorCount: 0);

        $this->service->recordFailure($this->ids->get('wh-1'), WebhookFailureStrategy::DisableOnThreshold);

        static::assertSame(1, $this->fetchErrorCount('wh-1'));
        static::assertTrue($this->fetchActive('wh-1'));
    }

    public function testRecordTerminalFailureDeactivatesAtThreshold(): void
    {
        $this->insertWebhook('wh-1', errorCount: WebhookFailureStrategy::MAX_ERROR_COUNT - 1);

        $this->service->recordFailure($this->ids->get('wh-1'), WebhookFailureStrategy::DisableOnThreshold);

        static::assertSame(0, $this->fetchErrorCount('wh-1'));
        static::assertFalse($this->fetchActive('wh-1'));
    }

    public function testRecordTerminalFailureIsNoOpOnInactiveWebhook(): void
    {
        $this->insertWebhook('wh-1', errorCount: 3, active: false);

        $this->service->recordFailure($this->ids->get('wh-1'), WebhookFailureStrategy::DisableOnThreshold);

        static::assertSame(3, $this->fetchErrorCount('wh-1'));
        static::assertFalse($this->fetchActive('wh-1'));
    }

    public function testRecordTerminalFailureIsNoOpOnMissingWebhook(): void
    {
        $this->service->recordFailure(Uuid::randomHex(), WebhookFailureStrategy::DisableOnThreshold);

        $this->addToAssertionCount(1);
    }

    public function testRecordTerminalFailureKeepsActiveUnderIgnoreStrategy(): void
    {
        $this->insertWebhook('wh-1', errorCount: WebhookFailureStrategy::MAX_ERROR_COUNT + 5);

        $this->service->recordFailure($this->ids->get('wh-1'), WebhookFailureStrategy::Ignore);

        static::assertSame(WebhookFailureStrategy::MAX_ERROR_COUNT + 6, $this->fetchErrorCount('wh-1'));
        static::assertTrue($this->fetchActive('wh-1'));
    }

    public function testRecordTerminalFailurePropagatesToRelatedWebhooks(): void
    {
        $this->insertWebhook('wh-1', errorCount: 0);
        $this->insertWebhook('wh-2', errorCount: 0);

        $this->service->recordFailure($this->ids->get('wh-1'), WebhookFailureStrategy::DisableOnThreshold);

        static::assertSame(1, $this->fetchErrorCount('wh-1'));
        static::assertSame(1, $this->fetchErrorCount('wh-2'));
    }

    public function testResetErrorCount(): void
    {
        $this->insertWebhook('wh-1', errorCount: 5);

        $this->service->resetErrorCount($this->ids->get('wh-1'));

        static::assertSame(0, $this->fetchErrorCount('wh-1'));
    }

    public function testResetErrorCountPropagatesToRelatedWebhooks(): void
    {
        $this->insertWebhook('wh-1', errorCount: 5);
        $this->insertWebhook('wh-2', errorCount: 5);

        $this->service->resetErrorCount($this->ids->get('wh-1'));

        static::assertSame(0, $this->fetchErrorCount('wh-1'));
        static::assertSame(0, $this->fetchErrorCount('wh-2'));
    }

    private function insertWebhook(string $key, int $errorCount = 0, bool $active = true): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'error_count' => $errorCount,
            'active' => (int) $active,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function fetchErrorCount(string $key): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }

    private function fetchActive(string $key): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT active FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
    }
}
