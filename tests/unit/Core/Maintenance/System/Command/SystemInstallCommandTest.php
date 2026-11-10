<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Installer\Finish\SystemLocker;
use Shopware\Core\Maintenance\System\Command\SystemInstallCommand;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @see \Shopware\Tests\Integration\Core\Maintenance\System\Command\SystemInstallCommandTest for the install.lock and .htaccess files
 */
#[Package('framework')]
#[CoversClass(SystemInstallCommand::class)]
class SystemInstallCommandTest extends TestCase
{
    use EnvTestBehaviour;

    public function testDefaultInstallFlow(): void
    {
        $systemInstallCmd = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install'], systemLocker: $this->createSystemLocker(expectLock: true));

        $result = $systemInstallCmd->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
    }

    public function testBasicSetupFlow(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands([
            'user:create',
            'sales-channel:create:storefront',
            'theme:change',
            'assets:install',
        ], systemLocker: $this->createSystemLocker(expectLock: true));

        $result = $command->run(new ArrayInput(['--basic-setup' => true]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
    }

    public function testAssetsInstallCanBeSkipped(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(systemLocker: $this->createSystemLocker(expectLock: true));

        $result = $command->run(new ArrayInput(['--skip-assets-install' => true]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
    }

    public function testSkipFirstRunWizardOption(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands([
            'assets:install',
            'system:config:set',
        ], systemLocker: $this->createSystemLocker(expectLock: true));

        $result = $command->run(new ArrayInput(['--skip-first-run-wizard' => true]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
    }

    public function testSkipWebInstallerWithFalsyEnvironmentVariable(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '0']);

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install'], systemLocker: $this->createSystemLocker(expectLock: true));

        $output = new BufferedOutput();
        $result = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::SUCCESS, $result);

        $outputContent = $output->fetch();
        static::assertStringNotContainsString('Skipping install.lock and .htaccess creation', $outputContent);
    }

    public function testSkipWebInstallerWithTruthyEnvironmentVariable(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '1']);

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install'], systemLocker: $this->createSystemLocker(expectLock: false));

        $output = new BufferedOutput();
        $result = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::SUCCESS, $result);

        $outputContent = $output->fetch();
        static::assertStringContainsString('Skipping install.lock and .htaccess creation', $outputContent);
    }

    public function testInstallLockNotCreatedOnFailure(): void
    {
        $connection = static::createStub(Connection::class);
        $connectionFactory = static::createStub(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($connection);
        $setupDatabaseAdapterMock = static::createStub(SetupDatabaseAdapter::class);

        $dispatcher = new EventDispatcher();
        $dispatched = false;
        $dispatcher->addListener(SystemInstallCompletedEvent::class, static function () use (&$dispatched): void {
            $dispatched = true;
        });

        $systemInstallCmd = new SystemInstallCommand(
            __DIR__,
            $setupDatabaseAdapterMock,
            $connectionFactory,
            static::createStub(CacheClearer::class),
            $this->createSystemLocker(expectLock: false),
            new NativeClock(),
            $dispatcher
        );

        $application = new class extends Application {
            public function has(string $name): bool
            {
                return true;
            }

            public function doRun(InputInterface $input, OutputInterface $output): int
            {
                return Command::FAILURE;
            }
        };

        $systemInstallCmd->setApplication($application);

        $result = $systemInstallCmd->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::FAILURE, $result);
        static::assertFalse($dispatched);
    }

    /**
     * Test that sub commands of the system:install fire the correct lifecycle events, instead of testing
     * them all, we just test one: database:migrate. If it works for one it most likely works for all.
     */
    public function testEventsForSubCommandsAreFired(): void
    {
        $connection = static::createStub(Connection::class);
        $connectionFactory = static::createStub(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($connection);
        $setupDatabaseAdapterMock = static::createStub(SetupDatabaseAdapter::class);

        $dispatcher = new EventDispatcher();

        $dispatcher->addListener(ConsoleEvents::TERMINATE, $listener = new class {
            public bool $terminateCalledForSubCommand = false;

            public function __invoke(ConsoleTerminateEvent $event): void
            {
                if ($event->getCommand()?->getName() === 'system:install') {
                    $this->terminateCalledForSubCommand = true;
                }
            }
        });

        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(
            new SystemInstallCommand(
                __DIR__,
                $setupDatabaseAdapterMock,
                $connectionFactory,
                static::createStub(CacheClearer::class),
                static::createStub(SystemLocker::class),
                new NativeClock(),
                new EventDispatcher()
            )
        );
        $application->setDispatcher($dispatcher);

        $appTester = new ApplicationTester($application);

        $appTester->run(['command' => 'system:install']);

        static::assertTrue($listener->terminateCalledForSubCommand);
    }

    public function testDispatchesSystemInstallCompletedEventOnSuccess(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatched = null;
        $dispatcher->addListener(SystemInstallCompletedEvent::class, static function (SystemInstallCompletedEvent $event) use (&$dispatched): void {
            $dispatched = $event;
        });

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install'], dispatcher: $dispatcher);

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertInstanceOf(SystemInstallCompletedEvent::class, $dispatched);
        static::assertSame(Context::SYSTEM_SCOPE, $dispatched->getContext()->getScope());
    }

    public function testDispatchesSystemInstallCompletedEventWhenWebInstallerIsSkipped(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '1']);

        $dispatcher = new EventDispatcher();
        $dispatched = false;
        $dispatcher->addListener(SystemInstallCompletedEvent::class, static function () use (&$dispatched): void {
            $dispatched = true;
        });

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install'], dispatcher: $dispatcher, systemLocker: $this->createSystemLocker(expectLock: false));

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertTrue($dispatched);
    }

    private function createSystemLocker(bool $expectLock): SystemLocker&MockObject
    {
        $systemLocker = $this->createMock(SystemLocker::class);
        $systemLocker->expects($expectLock ? $this->once() : $this->never())->method('lock');

        return $systemLocker;
    }

    /**
     * @param array<string> $expectedCommands
     */
    private function prepareCommandInstance(array $expectedCommands = [], ?EventDispatcherInterface $eventDispatcher = null, ?SystemLocker $systemLocker = null): SystemInstallCommand
    {
        $connection = static::createStub(Connection::class);
        $connectionFactory = static::createStub(DatabaseConnectionFactory::class);

        $connectionFactory->method('getConnection')->willReturn($connection);

        $setupDatabaseAdapterMock = static::createStub(SetupDatabaseAdapter::class);

        $systemInstallCmd = new SystemInstallCommand(
            __DIR__,
            $setupDatabaseAdapterMock,
            $connectionFactory,
            static::createStub(CacheClearer::class),
            $systemLocker ?? static::createStub(SystemLocker::class),
            new NativeClock(),
            $eventDispatcher ?? new EventDispatcher()
        );

        $application = $this->createMock(Application::class);
        $application->method('has')
            ->willReturn(true);

        $application->expects($this->exactly(\count($expectedCommands)))
            ->method('doRun')
            ->willReturn(Command::SUCCESS);

        $systemInstallCmd->setApplication($application);

        return $systemInstallCmd;
    }

    /**
     * @param array<string> $additionalCommands
     */
    private function prepareCommandInstanceWithDefaultInstallCommands(array $additionalCommands = [], ?EventDispatcherInterface $dispatcher = null, ?SystemLocker $systemLocker = null): SystemInstallCommand
    {
        $defaultCommands = [
            'database:migrate',
            'database:migrate-destructive',
            'system:configure-shop',
            'dal:refresh:index',
            'scheduled-task:register',
            'plugin:refresh',
            'theme:refresh',
            'theme:compile',
            'cache:clear',
        ];

        return $this->prepareCommandInstance(array_merge($defaultCommands, $additionalCommands), $dispatcher, $systemLocker);
    }
}
