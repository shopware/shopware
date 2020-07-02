<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Command\SalesChannelMaintenanceEnableCommand;
use Symfony\Component\Console\Tester\CommandTester;

class SalesChannelMaintenanceEnableCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelMaintenanceEnableCommand::class));
        $commandTester->execute([]);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:maintenance:enable\" returned errors:\n" . $commandTester->getDisplay()
        );
    }

    public function testUnknownSalesChannelIds(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelMaintenanceEnableCommand::class));
        $commandTester->execute(['ids' => [\Shopware\Core\Framework\Uuid\Uuid::randomHex()]]);

        static::assertEquals(
            'No sales channels were updated',
            $commandTester->getDisplay()
        );
    }

    public function testNoSalesChannelIds(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelMaintenanceEnableCommand::class));
        $commandTester->execute([]);

        static::assertEquals(
            'No sales channels were updated. Provide id(s) or run with --all option.',
            $commandTester->getDisplay()
        );
    }

    public function testOneSalesChannelIds(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelMaintenanceEnableCommand::class));
        $commandTester->execute(['ids' => [Defaults::SALES_CHANNEL]]);

        static::assertEquals(
            'Updated maintenance mode for 1 sales channel(s)',
            $commandTester->getDisplay()
        );
    }

    public function testAllSalesChannelIds(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelMaintenanceEnableCommand::class));
        $commandTester->execute(['--all' => true]);

        static::assertEquals(
            'Updated maintenance mode for 2 sales channel(s)',
            $commandTester->getDisplay()
        );
    }
}
