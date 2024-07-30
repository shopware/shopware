<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1720603803RemoveDefaultPaymentMethodFlows;

/**
 * @internal
 */
#[CoversClass(Migration1720603803RemoveDefaultPaymentMethodFlows::class)]
class Migration1720603803RemoveDefaultPaymentMethodFlowsTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testUpdate(): void
    {
        $initialInvalidFlows = $this->getInvalidFlows();
        $this->addTestFlows();

        $migration = new Migration1720603803RemoveDefaultPaymentMethodFlows();
        $migration->update($this->getContainer()->get(Connection::class));
        $migration->update($this->getContainer()->get(Connection::class));

        static::assertSame($initialInvalidFlows + 1, $this->getInvalidFlows());
    }

    public function testUpdateDestructive(): void
    {
        $this->addTestFlows();
        static::assertSame(1, $this->getDefaultPaymentMethodChangedFlows());

        $migration = new Migration1720603803RemoveDefaultPaymentMethodFlows();
        $migration->updateDestructive($this->getContainer()->get(Connection::class));
        $migration->updateDestructive($this->getContainer()->get(Connection::class));

        static::assertSame(0, $this->getDefaultPaymentMethodChangedFlows());
    }

    private function getInvalidFlows(): int
    {
        return (int) $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM `flow` WHERE `active` = 0 AND `invalid` = 1',
        );
    }

    private function getDefaultPaymentMethodChangedFlows(): int
    {
        return (int) $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM `flow` WHERE `event_name` = "checkout.customer.changed-payment-method"'
        );
    }

    private function addTestFlows(): void
    {
        $this->getContainer()->get(Connection::class)->insert('flow', [
            'id' => Uuid::randomBytes(),
            'name' => 'flowChangedCustomerDefaultPaymentMethod',
            'event_name' => 'checkout.customer.changed-payment-method',
            'active' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
