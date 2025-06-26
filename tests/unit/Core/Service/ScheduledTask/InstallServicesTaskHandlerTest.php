<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\ScheduledTask\InstallServicesTaskHandler;

/**
 * @internal
 */
#[CoversClass(InstallServicesTaskHandler::class)]
class InstallServicesTaskHandlerTest extends TestCase
{
    public function testAlwaysDelegatesToManager(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->once())
            ->method('install');

        $handler = new InstallServicesTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $manager,
        );

        $handler->run();
    }
}
