<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Command\StoreSkipFirstRunWizardCommand;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class StoreSkipFirstRunWizardCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCommand(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(StoreSkipFirstRunWizardCommand::class));
        $commandTester->execute([]);

        static::assertEquals(0, $commandTester->getStatusCode());

        $expected = 'First run wizard skipped.';
        static::assertStringContainsString($expected, $commandTester->getDisplay());
    }
}
