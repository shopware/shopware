<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\BundleConfigGenerator;
use Shopware\Core\Framework\Plugin\Command\BundleDumpCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class BundleDumpCommandTest extends TestCase
{
    private Filesystem $filesystem;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('bundle-dump-', true);
        $this->filesystem->mkdir($this->projectDir . '/var');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testDumperWritesFile(): void
    {
        $generator = static::createStub(BundleConfigGenerator::class);
        $generator->method('getConfig')->willReturn([]);

        $dumper = new BundleDumpCommand($generator, $this->projectDir);

        static::assertSame(0, $dumper->run(new StringInput(''), new NullOutput()));
        static::assertFileExists($this->projectDir . '/var/plugins.json');
    }

    public function testDumperWritesFileToSpecifiedFilePath(): void
    {
        $generator = static::createStub(BundleConfigGenerator::class);
        $generator->method('getConfig')->willReturn([]);

        $dumper = new BundleDumpCommand($generator, $this->projectDir);

        static::assertSame(0, $dumper->run(new StringInput('var/test.json'), new NullOutput()));
        static::assertFileExists($this->projectDir . '/var/test.json');
    }
}
