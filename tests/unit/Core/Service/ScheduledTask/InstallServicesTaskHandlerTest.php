<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\ScheduledTask\InstallServicesTaskHandler;

/**
 * @internal
 */
#[CoversClass(InstallServicesTaskHandler::class)]
class InstallServicesTaskHandlerTest extends TestCase
{
    public function testRunDelegatesToInstaller(): void
    {
        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects(static::once())->method('install');

        $handler = new InstallServicesTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $installer
        );

        $handler->run();
    }
}
