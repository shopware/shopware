<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
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
 */
#[CoversClass(SystemUpdateFinishCommand::class)]
class SystemUpdateFinishCommandTest extends TestCase
{
    private ContainerBuilder $container;

    private CollectingEventDispatcher $eventDispatcher;

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->container->set('event_dispatcher', $this->eventDispatcher);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->systemConfigService->set(UpdateController::UPDATE_PREVIOUS_VERSION_KEY, '6.4.0.0');

        $this->container->set(SystemConfigService::class, $this->systemConfigService);
    }

    public function testRunCommand(): void
    {
        $command = new SystemUpdateFinishCommand($this->container, '6.5.0.0');

        $application = $this->createMock(Application::class);
        $application
            ->expects(static::exactly(3))
            ->method('find')
            ->willReturn($this->createMock(Command::class));

        $application->method('doRun')->willReturn(Command::SUCCESS);

        $command->setApplication($application);
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

        $application = $this->createMock(Application::class);
        $migrationCommand = $this->createMock(Command::class);
        $migrationCommand->method('run')->willReturn(Command::SUCCESS);

        $application
            ->expects(static::exactly(2))
            ->method('find')
            ->willReturn($migrationCommand);

        $application->method('doRun')->willReturn(Command::SUCCESS);

        $command->setApplication($application);
        $tester = new CommandTester($command);

        $tester->execute(['--skip-asset-build' => true]);
        $tester->assertCommandIsSuccessful();

        $events = $this->eventDispatcher->getEvents();

        static::assertCount(2, $events);

        $event = $events[0];

        static::assertInstanceOf(UpdatePreFinishEvent::class, $event);

        static::assertTrue($event->getContext()->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING));
    }

    public function testSkipAll(): void
    {
        $command = new SystemUpdateFinishCommand($this->container, '6.5.0.0');
        $application = $this->createMock(Application::class);
        $application
            ->expects(static::never())
            ->method('find');

        $command->setApplication($application);

        $tester = new CommandTester($command);

        $tester->execute(['--skip-migrations' => true, '--skip-asset-build' => true]);
        $tester->assertCommandIsSuccessful();
    }
}
