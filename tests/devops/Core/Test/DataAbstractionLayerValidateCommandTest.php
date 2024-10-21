<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Command\DataAbstractionLayerValidateCommand;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class DataAbstractionLayerValidateCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(DataAbstractionLayerValidateCommand::class));
        $commandTester->execute([]);

        static::assertEquals(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console dal:validate\" returned errors:\n" . $commandTester->getDisplay()
        );
    }
}
