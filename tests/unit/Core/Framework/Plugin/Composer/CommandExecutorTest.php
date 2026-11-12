<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Composer;

use Composer\Console\Application;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\Composer\WorkingCopy;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CommandExecutor::class)]
class CommandExecutorTest extends TestCase
{
    private const COMPOSER_JSON = '{"name": "shopware/production", "require": {"shopware/core": "6.7.*"}}';

    private Filesystem $filesystem;

    private string $projectDir;

    /**
     * @var array{root: array{name: string, pretty_version: string, version: string, reference: string|null, type: string, install_path: string, aliases: string[], dev: bool}, versions: array<string, array{pretty_version?: string, version?: string, reference?: string|null, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]}>}
     */
    private array $installedVersionsBefore;

    private RecordingComposerApplication $application;

    private CommandExecutor $executor;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/command-executor-' . bin2hex(random_bytes(6));
        $this->filesystem->dumpFile($this->projectDir . '/composer.json', self::COMPOSER_JSON);
        $this->filesystem->dumpFile($this->projectDir . '/vendor/autoload.php', '<?php // live');
        $this->filesystem->dumpFile($this->projectDir . '/vendor/composer/installed.php', '<?php return [];');

        $this->installedVersionsBefore = InstalledVersions::getAllRawData()[0];
        InstalledVersions::reload([
            'root' => [
                'name' => 'shopware/production',
                'pretty_version' => '6.7.9999999.9999999-dev',
                'version' => '6.7.9999999.9999999-dev',
                'reference' => null,
                'type' => 'project',
                'install_path' => $this->projectDir . '/vendor/composer/../../',
                'aliases' => [],
                'dev' => false,
            ],
            'versions' => [
                'composer/composer' => [
                    'pretty_version' => '2.10.3',
                    'version' => '2.10.3.0',
                    'reference' => null,
                    'type' => 'library',
                    'install_path' => $this->projectDir . '/vendor/composer/composer',
                    'aliases' => [],
                    'dev_requirement' => false,
                ],
            ],
        ]);

        $this->application = new RecordingComposerApplication();
        $workingCopy = new WorkingCopy(
            $this->projectDir,
            $this->filesystem,
            new LockFactory(new InMemoryStore()),
            new CollectingMessageBus(),
        );
        $this->executor = new CommandExecutor($this->projectDir, $workingCopy, $this->application);
    }

    protected function tearDown(): void
    {
        InstalledVersions::reload($this->installedVersionsBefore);
        $this->filesystem->remove($this->projectDir);
    }

    public function testRequireKeepsLockedDependenciesOfThePlugin(): void
    {
        $this->executor->require('swag/test:1.0.0', 'SwagTest');

        static::assertNotNull($this->application->input);
        static::assertSame('require', $this->application->input->getParameterOption('command'));
        static::assertSame(['swag/test:1.0.0'], $this->application->input->getParameterOption('packages'));
        static::assertTrue($this->application->input->hasParameterOption('--update-with-dependencies'));
        static::assertTrue($this->application->input->hasParameterOption('--minimal-changes'));
        static::assertTrue($this->application->input->hasParameterOption('--no-scripts'));
    }

    public function testRequireRunsComposerInTheWorkingCopy(): void
    {
        $this->executor->require('swag/test:1.0.0', 'SwagTest');

        static::assertNotSame($this->projectDir . '/vendor', $this->application->vendorDirDuringRun);
        static::assertStringStartsWith($this->projectDir . '/', $this->application->vendorDirDuringRun);
        static::assertStringEqualsFile($this->projectDir . '/vendor/autoload.php', '<?php // written by Composer');
    }

    public function testRequirePinsComposerItselfOnlyWhileComposerRuns(): void
    {
        $this->executor->require('swag/test:1.0.0', 'SwagTest');

        static::assertSame('2.10.3.0', $this->application->composerJsonDuringRun['require']['composer/composer'] ?? null);
        static::assertJsonStringEqualsJsonFile($this->projectDir . '/composer.json', self::COMPOSER_JSON);
    }

    public function testRequireThrowsTheComposerOutputWhenComposerFails(): void
    {
        $this->application->exitCode = Command::FAILURE;
        $this->application->outputToWrite = 'Your requirements could not be resolved';

        $this->expectExceptionObject(PluginException::pluginComposerRequire('SwagTest', 'swag/test:1.0.0', 'Your requirements could not be resolved'));

        $this->executor->require('swag/test:1.0.0', 'SwagTest');
    }

    public function testRequireLeavesTheProjectUntouchedWhenComposerThrows(): void
    {
        $this->application->exception = new \RuntimeException('Composer crashed');

        try {
            $this->executor->require('swag/test:1.0.0', 'SwagTest');
            static::fail('The exception of the Composer run was swallowed');
        } catch (\RuntimeException $e) {
            static::assertSame('Composer crashed', $e->getMessage());
        }

        static::assertJsonStringEqualsJsonFile($this->projectDir . '/composer.json', self::COMPOSER_JSON);
        static::assertStringEqualsFile($this->projectDir . '/vendor/autoload.php', '<?php // live');
    }

    public function testRemoveKeepsLockedDependenciesOfThePlugin(): void
    {
        $this->executor->remove('swag/test', 'SwagTest');

        static::assertNotNull($this->application->input);
        static::assertSame('remove', $this->application->input->getParameterOption('command'));
        static::assertSame(['swag/test'], $this->application->input->getParameterOption('packages'));
        static::assertTrue($this->application->input->hasParameterOption('--minimal-changes'));
        static::assertTrue($this->application->input->hasParameterOption('--no-scripts'));
    }

    public function testRemoveThrowsTheComposerOutputWhenComposerFails(): void
    {
        $this->application->exitCode = Command::FAILURE;
        $this->application->outputToWrite = 'Package swag/test is not required';

        $this->expectExceptionObject(PluginException::pluginComposerRemove('SwagTest', 'swag/test', 'Package swag/test is not required'));

        $this->executor->remove('swag/test', 'SwagTest');
    }
}

/**
 * @internal
 */
class RecordingComposerApplication extends Application
{
    public ?ArrayInput $input = null;

    /**
     * @var array<string, mixed>
     */
    public array $composerJsonDuringRun = [];

    public string $vendorDirDuringRun = '';

    public int $exitCode = Command::SUCCESS;

    public string $outputToWrite = '';

    public ?\Throwable $exception = null;

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        if (!$input instanceof ArrayInput) {
            throw new \LogicException('CommandExecutor passes an ArrayInput');
        }

        $this->input = $input;
        $composerJsonPath = (string) getenv('COMPOSER');
        $this->composerJsonDuringRun = json_decode((string) file_get_contents($composerJsonPath), true, flags: \JSON_THROW_ON_ERROR);
        $this->vendorDirDuringRun = (string) ($this->composerJsonDuringRun['config']['vendor-dir'] ?? '');

        if ($this->exception !== null) {
            throw $this->exception;
        }

        $output?->write($this->outputToWrite);

        if ($this->exitCode === Command::SUCCESS) {
            (new Filesystem())->dumpFile($this->vendorDirDuringRun . '/autoload.php', '<?php // written by Composer');
        }

        return $this->exitCode;
    }
}
