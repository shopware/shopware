<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Command\Install;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(Install::class)]
class InstallTest extends TestCase
{
    public function testCommandWhenNoServicesAreInstalled(): void
    {
        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects(static::once())->method('install');

        $command = new Install($installer);
        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertStringContainsString('No services were installed', $tester->getDisplay());
    }

    public function testCommandWritesListOfInstalledServices(): void
    {
        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects(static::once())->method('install')->willReturn([
            'MyCoolService1',
            'MyCoolService2',
        ]);

        $command = new Install($installer);
        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertStringContainsString('MyCoolService1', $tester->getDisplay());
        static::assertStringContainsString('MyCoolService2', $tester->getDisplay());
    }
}
