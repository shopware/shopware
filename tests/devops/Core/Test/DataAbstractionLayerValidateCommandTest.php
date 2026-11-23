<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Command\DataAbstractionLayerValidateCommand;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
class DataAbstractionLayerValidateCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoValidationErrors(): void
    {
        // Major migrations are not run in the feature-flag test lane, so its schema cannot validate major definitions.
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $commandTester = new CommandTester(static::getContainer()->get(DataAbstractionLayerValidateCommand::class));
        $commandTester->execute([]);

        static::assertSame(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console dal:validate\" returned errors:\n" . $commandTester->getDisplay()
        );
    }
}
