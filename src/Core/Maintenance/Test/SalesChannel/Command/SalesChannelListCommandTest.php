<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\SalesChannel\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelListCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('core')]
class SalesChannelListCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(SalesChannelListCommand::class));
        $commandTester->execute([]);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:list\" returned errors:\n" . $commandTester->getDisplay()
        );
    }
}
