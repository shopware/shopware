<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Installer\Finish\SystemLocker;
use Shopware\Core\Maintenance\System\Command\SystemInstallCommand;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class SystemInstallCommandTest extends TestCase
{
    use EnvTestBehaviour;

    private Filesystem $filesystem;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('shopware-system-install-test', true);
        $this->filesystem->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testExecuteWhenInstallLockExists(): void
    {
        $this->filesystem->touch($this->projectDir . '/install.lock');

        $systemInstallCmd = $this->prepareCommandInstance();

        $output = new BufferedOutput();
        $result = $systemInstallCmd->run(new ArrayInput([
            '--shop-name' => 'Storefront',
            '--shop-email' => 'admin@gmail.com',
            '--shop-locale' => 'de-DE',
            '--shop-currency' => 'USD',
            '--basic-setup' => true,
            '--no-assign-theme' => true,
            '--drop-database' => true,
            '--create-database' => true,
        ]), $output);

        static::assertSame(Command::FAILURE, $result);
        static::assertStringContainsString('install.lock already exists', $output->fetch());
    }

    public function testForceOptionBypassesLockFile(): void
    {
        $this->filesystem->touch($this->projectDir . '/install.lock');

        $systemInstallCmd = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $systemInstallCmd->run(new ArrayInput(['--force' => true]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
    }

    public function testSkipWebInstallerIgnoresExistingLockFile(): void
    {
        $this->filesystem->touch($this->projectDir . '/install.lock');

        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '1']);

        $command = $this->prepareCommandInstance();

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::FAILURE, $result);
    }

    public function testHtaccessCreatedFromDistFile(): void
    {
        $this->createHtaccessDist('Test .htaccess content');

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertFileExists($this->projectDir . '/public/.htaccess');
        static::assertStringEqualsFile($this->projectDir . '/public/.htaccess', 'Test .htaccess content');
        static::assertFileExists($this->projectDir . '/install.lock');
    }

    public function testExistingHtaccessPreserved(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/public/.htaccess', 'Custom .htaccess content');

        // Create .htaccess.dist with different content
        $this->createHtaccessDist();

        $command = $this->prepareCommandInstanceWithDefaultInstallCommands(['assets:install']);

        $result = $command->run(new ArrayInput([]), new BufferedOutput());

        static::assertSame(Command::SUCCESS, $result);
        static::assertFileExists($this->projectDir . '/public/.htaccess');
        static::assertStringEqualsFile($this->projectDir . '/public/.htaccess', 'Custom .htaccess content');
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
        static::assertFileDoesNotExist($this->projectDir . '/public/.htaccess');
        static::assertFileDoesNotExist($this->projectDir . '/install.lock');
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
        static::assertFileExists($this->projectDir . '/public/.htaccess');
        static::assertStringEqualsFile($this->projectDir . '/public/.htaccess', 'Test .htaccess content');
        static::assertFileExists($this->projectDir . '/install.lock');
    }

    public function testHtaccessNotCreatedOnFailure(): void
    {
        $this->createHtaccessDist('Test .htaccess content');

        $connection = static::createStub(Connection::class);
        $connectionFactory = static::createStub(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($connection);
        $setupDatabaseAdapterMock = static::createStub(SetupDatabaseAdapter::class);

        $systemInstallCmd = new SystemInstallCommand(
            $this->projectDir,
            $setupDatabaseAdapterMock,
            $connectionFactory,
            static::createStub(CacheClearer::class),
            new SystemLocker($this->projectDir),
            new NativeClock(),
            new EventDispatcher()
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
        static::assertFileDoesNotExist($this->projectDir . '/public/.htaccess');
        static::assertFileDoesNotExist($this->projectDir . '/install.lock');
    }

    /**
     * @param array<string> $expectedCommands
     */
    private function prepareCommandInstance(array $expectedCommands = []): SystemInstallCommand
    {
        $connection = static::createStub(Connection::class);
        $connectionFactory = static::createStub(DatabaseConnectionFactory::class);

        $connectionFactory->method('getConnection')->willReturn($connection);

        $setupDatabaseAdapterMock = static::createStub(SetupDatabaseAdapter::class);

        $systemInstallCmd = new SystemInstallCommand(
            $this->projectDir,
            $setupDatabaseAdapterMock,
            $connectionFactory,
            static::createStub(CacheClearer::class),
            new SystemLocker($this->projectDir),
            new NativeClock(),
            new EventDispatcher()
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

    private function createHtaccessDist(string $content = 'Default .htaccess content'): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/public/.htaccess.dist', $content);
    }
}
