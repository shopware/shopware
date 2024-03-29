<?php declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\FileBackup;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(FileBackup::class)]
class FileBackupTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), static::name());

        if ($file === false) {
            static::fail('Cannot create temporary file');
        }

        $this->file = $file;
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->file, $this->file . '.bak']);
    }

    public function testBackupIsCreated(): void
    {
        file_put_contents($this->file, 'shopware');

        $backup = new FileBackup($this->file);
        $backup->backup();

        static::assertFileExists($this->file);
        static::assertFileExists($this->file . '.bak');
        static::assertFileEquals($this->file, $this->file . '.bak');
    }

    public function testRestore(): void
    {
        file_put_contents($this->file, 'shopware');

        $backup = new FileBackup($this->file);
        $backup->backup();

        static::assertFileExists($this->file);
        static::assertFileExists($this->file . '.bak');
        static::assertFileEquals($this->file, $this->file . '.bak');

        file_put_contents($this->file, 'shopwareiscool');

        $backup->restore();

        static::assertFileExists($this->file);
        static::assertFileDoesNotExist($this->file . '.bak');
        static::assertSame('shopware', file_get_contents($this->file));
    }

    public function testRemoveBackup(): void
    {
        file_put_contents($this->file, 'shopware');

        $backup = new FileBackup($this->file);
        $backup->backup();

        static::assertFileExists($this->file);
        static::assertFileExists($this->file . '.bak');

        $backup->remove();

        static::assertFileDoesNotExist($this->file . '.bak');
    }
}
