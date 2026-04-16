<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Service\WebhookStateRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class WebhookStateRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Connection $connection;

    private WebhookStateRepository $repository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->repository = static::getContainer()->get(WebhookStateRepository::class);
    }

    public function testIncrementErrorCountFromZero(): void
    {
        $this->insertWebhook('wh-1', errorCount: 0);

        $result = $this->repository->incrementErrorCount($this->ids->get('wh-1'));

        static::assertSame(1, $result);
        static::assertSame(1, $this->fetchErrorCount('wh-1'));
    }

    public function testIncrementErrorCountFromNine(): void
    {
        $this->insertWebhook('wh-1', errorCount: 9);

        $result = $this->repository->incrementErrorCount($this->ids->get('wh-1'));

        static::assertSame(10, $result);
        static::assertSame(10, $this->fetchErrorCount('wh-1'));
    }

    public function testIncrementErrorCountOnInactiveWebhookReturnsZero(): void
    {
        $this->insertWebhook('wh-1', active: false);

        $result = $this->repository->incrementErrorCount($this->ids->get('wh-1'));

        static::assertSame(0, $result);
        static::assertSame(0, $this->fetchErrorCount('wh-1'));
    }

    public function testIncrementErrorCountOnNonExistentWebhookReturnsZero(): void
    {
        $result = $this->repository->incrementErrorCount(Uuid::randomHex());

        static::assertSame(0, $result);
    }

    public function testResetErrorCount(): void
    {
        $this->insertWebhook('wh-1', errorCount: 5);

        $this->repository->resetErrorCount($this->ids->get('wh-1'));

        static::assertSame(0, $this->fetchErrorCount('wh-1'));
    }

    public function testDeactivate(): void
    {
        $this->insertWebhook('wh-1', errorCount: 3, active: true);

        $this->repository->deactivate($this->ids->get('wh-1'));

        $row = $this->connection->fetchAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes('wh-1')]
        );

        static::assertIsArray($row);
        static::assertSame(0, (int) $row['active']);
        static::assertSame(0, (int) $row['error_count']);
    }

    public function testIncrementErrorCountPropagatesToRelatedWebhooks(): void
    {
        $this->insertWebhook('wh-1', errorCount: 0);
        $this->insertWebhook('wh-2', errorCount: 0);

        $result = $this->repository->incrementErrorCount($this->ids->get('wh-1'));

        static::assertSame(1, $result);
        static::assertSame(1, $this->fetchErrorCount('wh-1'));
        static::assertSame(1, $this->fetchErrorCount('wh-2'));
    }

    public function testResetErrorCountPropagatesToRelatedWebhooks(): void
    {
        $this->insertWebhook('wh-1', errorCount: 5);
        $this->insertWebhook('wh-2', errorCount: 5);

        $this->repository->resetErrorCount($this->ids->get('wh-1'));

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
}
