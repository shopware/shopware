<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Maintenance\System\Command\SystemInstallCommand;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
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
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(SystemInstallCommand::class)]
class SystemInstallCommandTest extends TestCase
{
    use EnvTestBehaviour;

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove([
            __DIR__ . '/install.lock',
            __DIR__ . '/config',
        ]);
    }

    /**
     * @param array<string, mixed> $mockInputValues
     */
    #[DataProvider('dataProviderTestExecuteWhenInstallLockExists')]
    public function testExecuteWhenInstallLockExists(array $mockInputValues): void
    {
        touch(__DIR__ . '/install.lock');

        $systemInstallCmd = $this->prepareCommandInstance();

        $refMethod = ReflectionHelper::getMethod(SystemInstallCommand::class, 'execute');

        $result = $refMethod->invoke($systemInstallCmd, $this->getMockInput($mockInputValues), $this->createMock(OutputInterface::class));

        static::assertSame(Command::FAILURE, $result);
    }

    public static function dataProviderTestExecuteWhenInstallLockExists(): \Generator
    {
        yield 'Data provider for test execute failure' => [
            'mockInputValues' => [
                'force' => false,
                'shopName' => 'Storefront',
                'shopEmail' => 'admin@gmail.com',
                'shopLocale' => 'de-DE',
                'shopCurrency' => 'USD',
                'basicSetup' => true,
                'shopName_1' => 'Storefront',
                'shopLocale_1' => 'de-DE',
                'no-assign-theme' => true,
                'dropDatabase' => true,
                'createDatabase' => true,
            ],
        ];
    }

    public function testForceOptionBypassesLockFile(): void
    {
        touch(__DIR__ . '/install.lock');

        $systemInstallCmd = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $systemInstallCmd->run(new ArrayInput(['--force' => true]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
    }

    public function testInstallCreatesLock(): void
    {
        $systemInstallCmd = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $systemInstallCmd->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testDefaultInstallFlow(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(0, $result);
        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testBasicSetupFlow(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands([
            'user:create',
            'sales-channel:create:storefront',
            'theme:change',
            'assets:install',
        ]);

        $result = $command->run(new ArrayInput(['--basic-setup' => true]), new BufferedOutput());

        static::assertSame(0, $result);
    }

    public function testAssetsInstallCanBeSkipped(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands();

        $result = $command->run(new ArrayInput(['--skip-assets-install' => true]), new BufferedOutput());

        static::assertSame(0, $result);
    }

    public function testSkipFirstRunWizardOption(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands([
            'assets:install',
            'system:config:set',
        ]);

        $result = $command->run(new ArrayInput(['--skip-first-run-wizard' => true]), new BufferedOutput());

        static::assertSame(0, $result);
    }

    #[DataProvider('truthyEnvironmentVariableProvider')]
    public function testSkipWebInstallerWithTruthyEnvironmentVariable(string $envValue): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => $envValue]);

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $output = new BufferedOutput();
        $result = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::SUCCESS, $result);

        $outputContent = $output->fetch();
        static::assertStringContainsString('Skipping install.lock creation', $outputContent, \sprintf('Should skip lock creation when env var is "%s"', $envValue));
        static::assertFileDoesNotExist(__DIR__ . '/install.lock', \sprintf('Lock file should not exist when env var is "%s"', $envValue));
    }

    public static function truthyEnvironmentVariableProvider(): \Generator
    {
        yield 'string 1' => ['envValue' => '1'];
        yield 'string true' => ['envValue' => 'true'];
        yield 'string TRUE' => ['envValue' => 'TRUE'];
        yield 'string yes' => ['envValue' => 'yes'];
        yield 'string YES' => ['envValue' => 'YES'];
        yield 'string on' => ['envValue' => 'on'];
        yield 'string enabled' => ['envValue' => 'enabled'];

        yield 'string false (evaluates to true!)' => ['envValue' => 'false'];
        yield 'string FALSE' => ['envValue' => 'FALSE'];
        yield 'string no (evaluates to true!)' => ['envValue' => 'no'];
        yield 'string off (evaluates to true!)' => ['envValue' => 'off'];
        yield 'any non-empty string' => ['envValue' => 'anything'];
        yield 'string 2' => ['envValue' => '2'];
        yield 'spaces only' => ['envValue' => '   '];
    }

    #[DataProvider('falsyEnvironmentVariableProvider')]
    public function testCreateLockWithFalsyEnvironmentVariable(?string $envValue): void
    {
        if ($envValue !== null) {
            $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => $envValue]);
        }

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $output = new BufferedOutput();
        $result = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::SUCCESS, $result);

        $outputContent = $output->fetch();
        static::assertStringNotContainsString('Skipping install.lock creation', $outputContent, \sprintf('Should not skip lock creation when env var is "%s"', $envValue ?? 'null'));
        static::assertFileExists(__DIR__ . '/install.lock', \sprintf('Lock file should exist when env var is "%s"', $envValue ?? 'null'));
    }

    public static function falsyEnvironmentVariableProvider(): \Generator
    {
        yield 'string 0' => ['envValue' => '0'];
        yield 'empty string' => ['envValue' => ''];
        yield 'null (not set)' => ['envValue' => null];
    }

    #[DataProvider('allEnvironmentVariableProvider')]
    public function testSkipWebInstallerNeverBypassesCliSafety(?string $envValue): void
    {
        touch(__DIR__ . '/install.lock');

        if ($envValue !== null) {
            $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => $envValue]);
        }

        $command = $this->prepareCommandInstance();

        // Should always fail when install.lock exists, regardless of env var
        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::FAILURE, $result, \sprintf('Should fail with existing lock file regardless of env var "%s"', $envValue ?? 'null'));
    }

    public static function allEnvironmentVariableProvider(): \Generator
    {
        yield from self::truthyEnvironmentVariableProvider();
        yield from self::falsyEnvironmentVariableProvider();
    }

    public function testInstallLockNotCreatedOnFailure(): void
    {
        $connection = $this->createMock(Connection::class);
        $connectionFactory = $this->createMock(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($connection);
        $setupDatabaseAdapterMock = $this->createMock(SetupDatabaseAdapter::class);

        $systemInstallCmd = new SystemInstallCommand(
            __DIR__,
            $setupDatabaseAdapterMock,
            $connectionFactory,
            $this->createMock(CacheClearer::class)
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
        static::assertFileDoesNotExist(__DIR__ . '/install.lock');
    }

    /**
     * Test that sub commands of the system:install fire the correct lifecycle events, instead of testing
     * them all, we just test one: database:migrate. If it works for one it most likely works for all.
     */
    public function testEventsForSubCommandsAreFired(): void
    {
        $connection = $this->createMock(Connection::class);
        $connectionFactory = $this->createMock(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($connection);
        $setupDatabaseAdapterMock = $this->createMock(SetupDatabaseAdapter::class);

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
        $application->add(
            new SystemInstallCommand(
                __DIR__,
                $setupDatabaseAdapterMock,
                $connectionFactory,
                $this->createMock(CacheClearer::class)
            )
        );
        $application->setDispatcher($dispatcher);

        $appTester = new ApplicationTester($application);

        $appTester->run(['command' => 'system:install']);

        static::assertTrue($listener->terminateCalledForSubCommand);
    }

    /**
     * @param array<string> $expectedCommands
     */
    private function prepareCommandInstance(array $expectedCommands = []): SystemInstallCommand
    {
        $connection = $this->createMock(Connection::class);
        $connectionFactory = $this->createMock(DatabaseConnectionFactory::class);

        $connectionFactory->method('getConnection')->willReturn($connection);

        $setupDatabaseAdapterMock = $this->createMock(SetupDatabaseAdapter::class);
        $systemInstallCmd = new SystemInstallCommand(
            __DIR__,
            $setupDatabaseAdapterMock,
            $connectionFactory,
            $this->createMock(CacheClearer::class)
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
    private function prepareCommandInstanceWithDefaultInstallCommands(array $additionalCommands = []): SystemInstallCommand
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

        return $this->prepareCommandInstance(array_merge($defaultCommands, $additionalCommands));
    }

    /**
     * @param array<string, mixed> $mockInputValues
     */
    private function getMockInput(array $mockInputValues): InputInterface
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')
            ->willReturnOnConsecutiveCalls(...array_values($mockInputValues));

        return $input;
    }
}
