<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\BundleConfigGenerator;
use Shopware\Core\Framework\Plugin\Command\BundleDumpCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class BundleDumpCommandTest extends TestCase
{
    public function testDumperWritesFile(): void
    {
        $generator = $this->createMock(BundleConfigGenerator::class);
        $generator->method('getConfig')->willReturn([]);
        $tempDir = \sys_get_temp_dir() . '/' . uniqid(__FUNCTION__, true);
        (new Filesystem())->mkdir([$tempDir, $tempDir . '/var/']);

        $dumper = new BundleDumpCommand(
            $generator,
            $tempDir
        );

        static::assertSame(0, $dumper->run(new StringInput(''), new NullOutput()));
        static::assertFileExists($tempDir . '/var/plugins.json');

        (new Filesystem())->remove($tempDir);
    }

    public function testDumperWritesFileToSpecifiedFilePath(): void
    {
        $generator = $this->createMock(BundleConfigGenerator::class);
        $generator->method('getConfig')->willReturn([]);
        $tempDir = \sys_get_temp_dir() . '/' . uniqid(__FUNCTION__, true);
        (new Filesystem())->mkdir([$tempDir, $tempDir . '/var/']);

        $dumper = new BundleDumpCommand(
            $generator,
            $tempDir
        );

        static::assertSame(0, $dumper->run(new StringInput('var/test.json'), new NullOutput()));
        static::assertFileExists($tempDir . '/var/test.json');

        (new Filesystem())->remove($tempDir);
    }
}
