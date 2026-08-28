<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CleanupCustomerRecoveryTaskHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class CleanupCustomerRecoveryTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CleanupCustomerRecoveryTaskHandler $handler;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var CleanupCustomerRecoveryTaskHandler $handler */
        $handler = static::getContainer()->get(CleanupCustomerRecoveryTaskHandler::class);
        $this->handler = $handler;
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    public function testExpiredRecoveryIsDeleted(): void
    {
        $customerId = $this->createCustomer();

        $expiredAt = new \DateTime();
        $expiredAt->modify('-50 hour');
        $this->createCustomerRecovery($customerId, $expiredAt);

        $this->handler->run();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM customer_recovery WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );

        static::assertSame('0', (string) $count);
    }

    public function testNonExpiredRecoveryIsKept(): void
    {
        $customerId = $this->createCustomer();

        $recentAt = new \DateTime();
        $recentAt->modify('-1 hour');
        $this->createCustomerRecovery($customerId, $recentAt);

        $this->handler->run();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM customer_recovery WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)]
        );

        static::assertSame('1', (string) $count);
    }

    public function testMixedRecordsOnlyDeletesExpired(): void
    {
        $expiredCustomerId = $this->createCustomer();
        $recentCustomerId = $this->createCustomer();

        $expiredAt = new \DateTime();
        $expiredAt->modify('-50 hour');
        $this->createCustomerRecovery($expiredCustomerId, $expiredAt);

        $recentAt = new \DateTime();
        $recentAt->modify('-30 minutes');
        $this->createCustomerRecovery($recentCustomerId, $recentAt);

        $this->handler->run();

        $expiredCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM customer_recovery WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($expiredCustomerId)]
        );
        static::assertSame('0', (string) $expiredCount);

        $recentCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM customer_recovery WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($recentCustomerId)]
        );
        static::assertSame('1', (string) $recentCount);
    }

    private function createCustomer(): string
    {
        $context = Context::createDefaultContext();
        $customerRepository = static::getContainer()->get('customer.repository');

        $customerNumber = 'TEST-' . Uuid::randomHex();

        $customer = (new CustomerBuilder($this->ids, $customerNumber))
            ->add('email', $customerNumber . '@example.com');

        $customerRepository->create([$customer->build()], $context);

        return $this->ids->get($customerNumber);
    }

    private function createCustomerRecovery(string $customerId, \DateTime $createdAt): void
    {
        $this->connection->insert('customer_recovery', [
            'id' => Uuid::randomBytes(),
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'hash' => Uuid::randomHex(),
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
