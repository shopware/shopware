<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
 * @package system-settings
 *
 * @internal
 */
#[CoversClass(SystemInstallCommand::class)]
class SystemInstallCommandTest extends TestCase
{
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

        static::assertEquals($result, Command::FAILURE);
    }

    public static function dataProviderTestExecuteWhenInstallLockExists(): \Generator
    {
        yield 'Data provider for test execute failure' => [
            'Mock method getOption from input' => [
                'force' => false,
                'shopName' => 'Storefront',
                'shopEmail' => 'admin@gmail.com',
                'shopLocale' => 'de-DE',
                'shopCurrency' => 'USD',
                'skipJwtKeysGeneration' => true,
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
        $command = $this->prepareCommandInstance([
            'database:migrate',
            'database:migrate-destructive',
            'system:configure-shop',
            'dal:refresh:index',
            'scheduled-task:register',
            'plugin:refresh',
            'theme:refresh',
            'theme:compile',
            'assets:install',
            'cache:clear',
        ]);

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertEquals(0, $result);
    }

    public function testBasicSetupFlow(): void
    {
        $command = $this->prepareCommandInstance([
            'database:migrate',
            'database:migrate-destructive',
            'system:configure-shop',
            'dal:refresh:index',
            'scheduled-task:register',
            'plugin:refresh',
            'theme:refresh',
            'theme:compile',
            'user:create',
            'sales-channel:create:storefront',
            'theme:change',
            'assets:install',
            'cache:clear',
        ]);

        $result = $command->run(new ArrayInput(['--basic-setup' => true]), new BufferedOutput());

        static::assertEquals(0, $result);
    }

    public function testJwtGenerationCanBeSkipped(): void
    {
        $command = $this->prepareCommandInstance([
            'database:migrate',
            'database:migrate-destructive',
            'system:configure-shop',
            'dal:refresh:index',
            'scheduled-task:register',
            'plugin:refresh',
            'theme:refresh',
            'theme:compile',
            'assets:install',
            'cache:clear',
        ]);

        $result = $command->run(new ArrayInput(['--skip-jwt-keys-generation' => true]), new BufferedOutput());

        static::assertEquals(0, $result);
    }

    public function testAssetsInstallCanBeSkipped(): void
    {
        $command = $this->prepareCommandInstance([
            'database:migrate',
            'database:migrate-destructive',
            'system:configure-shop',
            'dal:refresh:index',
            'scheduled-task:register',
            'plugin:refresh',
            'theme:refresh',
            'theme:compile',
            'cache:clear',
        ]);

        $result = $command->run(new ArrayInput(['--skip-assets-install' => true]), new BufferedOutput());

        static::assertEquals(0, $result);
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

        $dispatcher->addListener(ConsoleEvents::TERMINATE, $listener = new class() {
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
            new SystemInstallCommand(__DIR__, $setupDatabaseAdapterMock, $connectionFactory)
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
        $systemInstallCmd = new SystemInstallCommand(__DIR__, $setupDatabaseAdapterMock, $connectionFactory);

        $application = $this->createMock(Application::class);
        $application->method('has')
            ->willReturn(true);

        $application->expects(static::exactly(\count($expectedCommands)))
            ->method('doRun')
            ->willReturn(Command::SUCCESS);

        $systemInstallCmd->setApplication($application);

        return $systemInstallCmd;
    }

    private function getMockInput(mixed $mockInputValues): InputInterface
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')
            ->willReturnOnConsecutiveCalls(...array_values($mockInputValues));

        return $input;
    }
}
