<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Visibility;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class FilesystemFactoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/shopware-filesystem-factory-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->root);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testCreateLocalAdapterEnforcesFilePermissionsByDefault(): void
    {
        $file = $this->root . '/test.txt';
        (new Filesystem())->touch($file);
        (new Filesystem())->chmod($file, 0666);

        $factory = new FilesystemFactory([new LocalFactory()]);
        $adapter = $factory->factory([
            'type' => 'local',
            'visibility' => Visibility::PRIVATE,
            'config' => [
                'root' => $this->root,
                'file' => [
                    'private' => 0400,
                ],
            ],
        ]);

        $adapter->write('test.txt', 'test');

        static::assertSame(0400, $this->getPermissions($file));
    }

    public function testCreateLocalAdapterCanSkipFilePermissionEnforcement(): void
    {
        $file = $this->root . '/test.txt';
        (new Filesystem())->touch($file);
        (new Filesystem())->chmod($file, 0666);

        $factory = new FilesystemFactory([new LocalFactory()]);
        $adapter = $factory->factory([
            'type' => 'local',
            'visibility' => Visibility::PRIVATE,
            'config' => [
                'root' => $this->root,
                'file' => [
                    'private' => 0400,
                ],
                'enforce_file_permissions' => false,
            ],
        ]);

        $adapter->write('test.txt', 'test');

        static::assertSame(0666, $this->getPermissions($file));
    }

    private function getPermissions(string $file): int
    {
        clearstatcache(true, $file);

        return fileperms($file) & 0777;
    }
}
