<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Manager;
use Shopware\Core\Service\ScheduledTask\InstallServicesTaskHandler;

/**
 * @internal
 */
#[CoversClass(InstallServicesTaskHandler::class)]
class InstallServicesTaskHandlerTest extends TestCase
{
    public function testDelegatesToInstallerIfServicesAreEnabled(): void
    {
        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects($this->once())
            ->method('install');

        $manager = $this->createMock(Manager::class);
        $manager->method('isDisabled')
            ->willReturn(false);

        $handler = new InstallServicesTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $installer,
            $manager,
        );

        $handler->run();
    }

    public function testDoesNotDelegateToInstallerIfServicesAreDisabled(): void
    {
        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects($this->never())
            ->method('install');

        $manager = $this->createMock(Manager::class);
        $manager->method('isDisabled')
            ->willReturn(true);

        $handler = new InstallServicesTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $installer,
            $manager,
        );

        $handler->run();
    }
}
