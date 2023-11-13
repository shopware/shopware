<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Maintenance\System\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Update\Api\UpdateController;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Update\Event\UpdatePreFinishEvent;
use Shopware\Core\Maintenance\System\Command\SystemUpdateFinishCommand;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @covers \Shopware\Core\Maintenance\System\Command\SystemUpdateFinishCommand
 */
class SystemUpdateFinishCommandTest extends TestCase
{
    private ContainerBuilder $container;

    private CollectingEventDispatcher $eventDispatcher;

    private StaticSystemConfigService $systemConfigService;

    private Application&MockObject $application;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->container->set('event_dispatcher', $this->eventDispatcher);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->systemConfigService->set(UpdateController::UPDATE_PREVIOUS_VERSION_KEY, '6.4.0.0');

        $this->container->set(SystemConfigService::class, $this->systemConfigService);

        $this->application = $this->createMock(Application::class);
        $this->application
            ->expects(static::exactly(2))
            ->method('find')
            ->willReturn($this->createMock(Command::class));
        $this->application->method('doRun')->willReturn(Command::SUCCESS);
    }

    public function testRunCommand(): void
    {
        $command = new SystemUpdateFinishCommand($this->container, '6.5.0.0');

        $command->setApplication($this->application);
        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $events = $this->eventDispatcher->getEvents();

        static::assertCount(2, $events);

        $event = $events[0];

        static::assertInstanceOf(UpdatePreFinishEvent::class, $event);

        static::assertSame('6.5.0.0', $event->getNewVersion());
        static::assertSame('6.4.0.0', $event->getOldVersion());

        $finishEvent = $events[1];

        static::assertInstanceOf(UpdatePostFinishEvent::class, $finishEvent);

        static::assertFalse($event->getContext()->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING));
    }

    public function testRunCommandSkipAssetBuild(): void
    {
        $command = new SystemUpdateFinishCommand($this->container, '6.5.0.0');

        $command->setApplication($this->application);
        $tester = new CommandTester($command);

        $tester->execute(['--skip-asset-build' => true]);
        $tester->assertCommandIsSuccessful();

        $events = $this->eventDispatcher->getEvents();

        static::assertCount(2, $events);

        $event = $events[0];

        static::assertInstanceOf(UpdatePreFinishEvent::class, $event);

        static::assertTrue($event->getContext()->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING));
    }
}
