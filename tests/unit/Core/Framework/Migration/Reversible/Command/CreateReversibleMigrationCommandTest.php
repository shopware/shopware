<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\Reversible\Command\CreateReversibleMigrationCommand;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\RelocatablePlugin;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateReversibleMigrationCommand::class)]
class CreateReversibleMigrationCommandTest extends TestCase
{
    private const TIMESTAMP = 1787465993;

    private string $pluginDirectory;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->pluginDirectory = sys_get_temp_dir() . '/sw-reversible-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->pluginDirectory . '/Migration');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->pluginDirectory);
    }

    public function testCreatesAMigrationFromTheTemplate(): void
    {
        $tester = new CommandTester($this->command());
        $tester->execute(['--plugin' => 'RelocatablePlugin', '--name' => 'CreateReview']);

        $tester->assertCommandIsSuccessful();

        $path = $this->pluginDirectory . '/Migration/Migration' . self::TIMESTAMP . 'CreateReview.php';
        static::assertFileExists($path);

        $contents = (string) file_get_contents($path);
        static::assertStringContainsString('namespace Swag\\Reversible\\Migration;', $contents);
        static::assertStringContainsString('class Migration' . self::TIMESTAMP . 'CreateReview extends Migration', $contents);
        static::assertStringContainsString('return ' . self::TIMESTAMP . ';', $contents);
        static::assertStringContainsString('public function up(UpMigrationContext $context): void', $contents);
        static::assertStringContainsString('public function down(DownMigrationContext $context): void', $contents);
    }

    public function testCreatesAMigrationWithoutAName(): void
    {
        $tester = new CommandTester($this->command());
        $tester->execute(['--plugin' => 'RelocatablePlugin']);

        $tester->assertCommandIsSuccessful();
        static::assertFileExists($this->pluginDirectory . '/Migration/Migration' . self::TIMESTAMP . '.php');
    }

    public function testRejectsAForbiddenName(): void
    {
        $this->expectExceptionObject(
            MigrationException::invalidArgument('Migration name contains forbidden characters!')
        );

        (new CommandTester($this->command()))->execute(['--plugin' => 'RelocatablePlugin', '--name' => 'Not Valid!']);
    }

    public function testRequiresAPlugin(): void
    {
        $this->expectExceptionObject(
            MigrationException::invalidArgument('Please specify the plugin the migration belongs to via --plugin.')
        );

        (new CommandTester($this->command()))->execute([]);
    }

    public function testRefusesToOverwriteAnExistingMigration(): void
    {
        $path = $this->pluginDirectory . '/Migration/Migration' . self::TIMESTAMP . 'CreateReview.php';
        $this->filesystem->dumpFile($path, '<?php // pre-existing');

        try {
            (new CommandTester($this->command()))->execute(['--plugin' => 'RelocatablePlugin', '--name' => 'CreateReview']);
            static::fail('Expected the command to refuse overwriting an existing migration.');
        } catch (MigrationException $exception) {
            static::assertStringContainsString('already exists', $exception->getMessage());
        }

        static::assertSame('<?php // pre-existing', file_get_contents($path));
    }

    private function command(): CreateReversibleMigrationCommand
    {
        $plugin = (new RelocatablePlugin(true, $this->pluginDirectory))->relocateTo($this->pluginDirectory);

        $collection = new KernelPluginCollection();
        $collection->add($plugin);

        return new CreateReversibleMigrationCommand(
            $collection,
            $this->filesystem,
            new MockClock((new \DateTimeImmutable())->setTimestamp(self::TIMESTAMP))
        );
    }
}
