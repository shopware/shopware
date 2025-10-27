<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Installer\Finish\SystemLocker;
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
            __DIR__ . '/public/.htaccess',
            __DIR__ . '/public/.htaccess.dist',
            __DIR__ . '/public',
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

        $refMethod = new \ReflectionMethod(SystemInstallCommand::class, 'execute');

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

    public function testDefaultInstallFlow(): void
    {
        $systemInstallCmd = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $systemInstallCmd->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
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

        static::assertSame(Command::SUCCESS, $result);
        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testAssetsInstallCanBeSkipped(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands();

        $result = $command->run(new ArrayInput(['--skip-assets-install' => true]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testSkipFirstRunWizardOption(): void
    {
        $command = $this->prepareCommandInstanceWithDefaultInstallCommands([
            'assets:install',
            'system:config:set',
        ]);

        $result = $command->run(new ArrayInput(['--skip-first-run-wizard' => true]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testSkipWebInstallerWithFalsyEnvironmentVariable(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '0']);

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $output = new BufferedOutput();
        $result = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::SUCCESS, $result);

        $outputContent = $output->fetch();
        static::assertStringNotContainsString('Skipping install.lock and .htaccess creation', $outputContent);
        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testSkipWebInstallerWithTruthyEnvironmentVariable(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '1']);

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $output = new BufferedOutput();
        $result = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::SUCCESS, $result);

        $outputContent = $output->fetch();
        static::assertStringContainsString('Skipping install.lock and .htaccess creation', $outputContent);
        static::assertFileDoesNotExist(__DIR__ . '/install.lock');
    }

    public function testForceOptionBypassesLockFile(): void
    {
        touch(__DIR__ . '/install.lock');

        $systemInstallCmd = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $systemInstallCmd->run(new ArrayInput(['--force' => true]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
    }

    public function testSkipWebInstallerIgnoresExistingLockFile(): void
    {
        touch(__DIR__ . '/install.lock');

        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '1']);

        $command = $this->prepareCommandInstance();

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::FAILURE, $result);
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
            $this->createMock(CacheClearer::class),
            $this->createMock(SystemLocker::class)
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

    public function testHtaccessCreatedFromDistFile(): void
    {
        $this->createHtaccessDist('Test .htaccess content');

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertFileExists(__DIR__ . '/public/.htaccess');
        static::assertStringEqualsFile(__DIR__ . '/public/.htaccess', 'Test .htaccess content');
    }

    public function testExistingHtaccessPreserved(): void
    {
        $publicDir = __DIR__ . '/public';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }
        file_put_contents($publicDir . '/.htaccess', 'Custom .htaccess content');

        // Create .htaccess.dist with different content
        $this->createHtaccessDist();

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertFileExists(__DIR__ . '/public/.htaccess');
        static::assertStringEqualsFile(__DIR__ . '/public/.htaccess', 'Custom .htaccess content');
    }

    public function testHtaccessSkippedWithWebInstallerSkip(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '1']);
        $this->createHtaccessDist('Test .htaccess content');

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $output = new BufferedOutput();
        $result = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::SUCCESS, $result);

        $outputContent = $output->fetch();
        static::assertStringContainsString('Skipping install.lock and .htaccess creation', $outputContent);
        static::assertFileDoesNotExist(__DIR__ . '/public/.htaccess');
        static::assertFileDoesNotExist(__DIR__ . '/install.lock');
    }

    public function testHtaccessCreatedWithWebInstallerNotSkipped(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '0']);
        $this->createHtaccessDist('Test .htaccess content');

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $output = new BufferedOutput();
        $result = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::SUCCESS, $result);

        $outputContent = $output->fetch();
        static::assertStringNotContainsString('Skipping install.lock and .htaccess creation', $outputContent);
        static::assertFileExists(__DIR__ . '/public/.htaccess');
        static::assertStringEqualsFile(__DIR__ . '/public/.htaccess', 'Test .htaccess content');
        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testHtaccessNotCreatedOnFailure(): void
    {
        $this->createHtaccessDist('Test .htaccess content');

        $connection = $this->createMock(Connection::class);
        $connectionFactory = $this->createMock(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($connection);
        $setupDatabaseAdapterMock = $this->createMock(SetupDatabaseAdapter::class);

        $systemInstallCmd = new SystemInstallCommand(
            __DIR__,
            $setupDatabaseAdapterMock,
            $connectionFactory,
            $this->createMock(CacheClearer::class),
            $this->createMock(SystemLocker::class)
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
        static::assertFileDoesNotExist(__DIR__ . '/public/.htaccess');
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
                $this->createMock(CacheClearer::class),
                $this->createMock(SystemLocker::class)
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
    private function prepareCommandInstance(array $expectedCommands = [], string $projectDir = __DIR__): SystemInstallCommand
    {
        $connection = $this->createMock(Connection::class);
        $connectionFactory = $this->createMock(DatabaseConnectionFactory::class);

        $connectionFactory->method('getConnection')->willReturn($connection);

        $setupDatabaseAdapterMock = $this->createMock(SetupDatabaseAdapter::class);
        $systemLocker = new SystemLocker($projectDir);

        $systemInstallCmd = new SystemInstallCommand(
            $projectDir,
            $setupDatabaseAdapterMock,
            $connectionFactory,
            $this->createMock(CacheClearer::class),
            $systemLocker
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
    private function prepareCommandInstanceWithDefaultInstallCommands(array $additionalCommands = [], string $projectDir = __DIR__): SystemInstallCommand
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

        return $this->prepareCommandInstance(array_merge($defaultCommands, $additionalCommands), $projectDir);
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

    private function createHtaccessDist(string $content = 'Default .htaccess content'): void
    {
        $publicDir = __DIR__ . '/public';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }
        file_put_contents($publicDir . '/.htaccess.dist', $content);
    }
}
