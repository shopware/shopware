<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\ScheduledTask\InstallServicesTask;
use Shopware\Core\Service\ScheduledTask\InstallServicesTaskHandler;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstallServicesTaskHandler::class)]
class InstallServicesTaskHandlerTest extends TestCase
{
    public function testServicesAreInstalledWhenTheFirstRunWizardIsNotPending(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->once())->method('reconcile');

        $this->createHandler($manager, frwPending: false)->run();
    }

    public function testServicesAreNotInstalledWhileTheFirstRunWizardIsPending(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->never())->method('reconcile');

        $this->createHandler($manager, frwPending: true)->run();
    }

    public function testTheTaskKeepsItsScheduleWhenTheFirstRunWizardIsNotPending(): void
    {
        $handler = $this->createHandler(static::createStub(LifecycleManager::class), frwPending: false);

        static::assertNull($handler->getNextExecutionTime(new InstallServicesTask(), new ScheduledTaskEntity()));
    }

    public function testTheTaskRetriesInFifteenMinutesWhileTheFirstRunWizardIsPending(): void
    {
        $handler = $this->createHandler(static::createStub(LifecycleManager::class), frwPending: true);
        $handler->setClock(new MockClock('2026-10-06 12:00:00'));

        static::assertEquals(
            new \DateTimeImmutable('2026-10-06 12:15:00'),
            $handler->getNextExecutionTime(new InstallServicesTask(), new ScheduledTaskEntity())
        );
    }

    private function createHandler(LifecycleManager $manager, bool $frwPending): InstallServicesTaskHandler
    {
        $firstRunWizardService = static::createStub(FirstRunWizardService::class);
        $firstRunWizardService->method('frwShouldRun')->willReturn($frwPending);

        return new InstallServicesTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $manager,
            $firstRunWizardService,
        );
    }
}
