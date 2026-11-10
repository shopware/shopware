<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoRealFilesystemInUnitTestsRule;

use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class Cases extends TestCase
{
    public function testTempLocations(): void
    {
        $dir = sys_get_temp_dir();
        $file = tempnam($dir, 'sw');
        $handle = tmpfile();
        $cwd = getcwd();
        chdir($dir);
        \sys_get_temp_dir(); // the global namespace prefix does not change the function
    }

    public function testNativeWrites(string $dir): void
    {
        mkdir($dir);
        rmdir($dir);
        unlink($dir . '/a');
        rename($dir . '/a', $dir . '/b');
        copy($dir . '/a', $dir . '/b');
        touch($dir . '/a');
        symlink($dir . '/a', $dir . '/b');
        link($dir . '/a', $dir . '/b');
        chmod($dir . '/a', 0o644);
        chown($dir . '/a', 'www');
        chgrp($dir . '/a', 'www');
        file_put_contents($dir . '/a', 'content');
    }

    public function testSymfonyFilesystemWrites(string $dir): void
    {
        $filesystem = new Filesystem();

        $filesystem->mkdir($dir);
        $filesystem->dumpFile($dir . '/a', 'content');
        $filesystem->appendToFile($dir . '/a', 'content');
        $filesystem->touch($dir . '/a');
        $filesystem->copy($dir . '/a', $dir . '/b');
        $filesystem->rename($dir . '/a', $dir . '/b');
        $filesystem->mirror($dir, $dir . '/copy');
        $filesystem->symlink($dir . '/a', $dir . '/b');
        $filesystem->hardlink($dir . '/a', $dir . '/b');
        $filesystem->chmod($dir . '/a', 0o644);
        $filesystem->chown($dir . '/a', 'www');
        $filesystem->chgrp($dir . '/a', 'www');
        $filesystem->tempnam($dir, 'sw');
        $filesystem->remove($dir);
    }

    public function testAllowed(): void
    {
        $fixture = file_get_contents(__DIR__ . '/fixture.json'); // reading a checked-in fixture
        $exists = is_file(__DIR__ . '/fixture.json');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(true); // configuring a stub is no write
        $filesystem->exists('/app/a');
        $filesystem->readFile('/app/a');

        $static = new StaticFilesystem(['a' => 'content']);
        $static->read('a');

        $flysystem = new FlysystemFilesystem(new InMemoryFilesystemAdapter());
        $flysystem->write('a', 'content'); // an in-memory Flysystem never reaches the disk

        $this->remove('a'); // same method name on another class
        $this->mkdir('a');
    }

    private function remove(string $path): void
    {
    }

    private function mkdir(string $path): void
    {
    }
}

/**
 * @internal
 */
class Helper
{
    public function prepare(): string
    {
        return sys_get_temp_dir(); // helpers inside the unit namespace are covered too
    }
}
