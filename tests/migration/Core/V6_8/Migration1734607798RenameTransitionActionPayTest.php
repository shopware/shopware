<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_8\Migration1734607798RenameTransitionActionPay;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1734607798RenameTransitionActionPay::class)]
class Migration1734607798RenameTransitionActionPayTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        // remove duplicates before, as Migration1734604008RemoveDuplicateTransitionsForActionPay would do before
        $this->removeDuplicates($connection);

        $migration = new Migration1734607798RenameTransitionActionPay();
        $migration->update($connection);
        $migration->update($connection);

        $transitions = $connection->executeQuery('SELECT `action_name` FROM `state_machine_transition`')->fetchFirstColumn();

        static::assertNotContains('pay', $transitions);
        static::assertNotContains('pay_partially', $transitions);

        static::assertContains('paid', $transitions);
        static::assertContains('paid_partially', $transitions);
    }

    private function removeDuplicates(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM `state_machine_transition` WHERE `action_name` = ?', ['paid']);
        $connection->executeStatement('DELETE FROM `state_machine_transition` WHERE `action_name` = ?', ['paid_partially']);
    }
}
