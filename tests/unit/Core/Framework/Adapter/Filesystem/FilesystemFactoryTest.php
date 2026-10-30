<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FilesystemFactory::class)]
class FilesystemFactoryTest extends TestCase
{
    private string $temporaryDirectory;

    protected function tearDown(): void
    {
        if (isset($this->temporaryDirectory)) {
            (new SymfonyFilesystem())->remove($this->temporaryDirectory);
        }
    }

    public function testMultipleSame(): void
    {
        static::expectExceptionObject(AdapterException::duplicateFilesystemFactory('local'));
        new FilesystemFactory([new LocalFactory(), new LocalFactory()]);
    }

    public function testCreateLocalAdapter(): void
    {
        $factory = new FilesystemFactory([new LocalFactory()]);
        $adapter = $factory->factory([
            'type' => 'local',
            'visibility' => Visibility::PUBLIC,
            'config' => [
                'root' => __DIR__,
            ],
        ]);

        static::assertSame(Visibility::PUBLIC, $adapter->visibility(''));
    }

    public function testCreateLocalAdapterEnforcesFilePermissionsByDefault(): void
    {
        $root = $this->createTemporaryDirectory();
        $file = $root . '/test.txt';
        touch($file);
        chmod($file, 0666);

        $factory = new FilesystemFactory([new LocalFactory()]);
        $adapter = $factory->factory([
            'type' => 'local',
            'visibility' => Visibility::PRIVATE,
            'config' => [
                'root' => $root,
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
        $root = $this->createTemporaryDirectory();
        $file = $root . '/test.txt';
        touch($file);
        chmod($file, 0666);

        $factory = new FilesystemFactory([new LocalFactory()]);
        $adapter = $factory->factory([
            'type' => 'local',
            'visibility' => Visibility::PRIVATE,
            'config' => [
                'root' => $root,
                'file' => [
                    'private' => 0400,
                ],
                'enforce_file_permissions' => false,
            ],
        ]);

        $adapter->write('test.txt', 'test');

        static::assertSame(0666, $this->getPermissions($file));
    }

    public function testPublicUrlFollowsTheCurrentRequestWithoutConfiguredUrl(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));

        $filesystem = (new FilesystemFactory([new LocalFactory()], $requestStack))->factory([
            'type' => 'local',
            'config' => ['root' => __DIR__],
        ]);

        static::assertSame('https://first.example/media/a.jpg', $filesystem->publicUrl('media/a.jpg'));

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));

        static::assertSame('https://second.example/media/a.jpg', $filesystem->publicUrl('media/a.jpg'));
    }

    public function testPublicUrlKeepsTheConfiguredUrl(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));

        $filesystem = (new FilesystemFactory([new LocalFactory()], $requestStack))->factory([
            'type' => 'local',
            'url' => 'https://cdn.example',
            'config' => ['root' => __DIR__],
        ]);

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));

        static::assertSame('https://cdn.example/media/a.jpg', $filesystem->publicUrl('media/a.jpg'));
    }

    public function testPublicUrlFollowsTheCurrentRequestForAdapterFactories(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));

        $filesystem = (new FilesystemFactory([new InMemoryAdapterFactory()], $requestStack))->factory([
            'type' => 'memory',
        ]);

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));

        static::assertInstanceOf(LeagueFilesystem::class, $filesystem);
        static::assertSame('https://second.example/media/a.jpg', $filesystem->publicUrl('media/a.jpg'));
    }

    public function testPrivateFilesystemHasNoPublicUrl(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));

        $filesystem = (new FilesystemFactory([new LocalFactory()], $requestStack))->privateFactory([
            'type' => 'local',
            'config' => ['root' => __DIR__],
        ]);

        $this->expectException(UnableToGeneratePublicUrl::class);
        $filesystem->publicUrl('media/a.jpg');
    }

    public function testCreateUnknown(): void
    {
        $factory = new FilesystemFactory([new LocalFactory()]);
        static::expectExceptionObject(AdapterException::filesystemFactoryNotFound('test2'));
        $factory->factory([
            'type' => 'test2',
        ]);
    }

    private function createTemporaryDirectory(): string
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/shopware-filesystem-factory-' . bin2hex(random_bytes(4));
        mkdir($this->temporaryDirectory);

        return $this->temporaryDirectory;
    }

    private function getPermissions(string $file): int
    {
        clearstatcache(true, $file);

        return fileperms($file) & 0777;
    }
}

/**
 * @internal
 */
class InMemoryAdapterFactory implements AdapterFactoryInterface
{
    public function create(array $config): FilesystemAdapter
    {
        return new InMemoryFilesystemAdapter();
    }

    public function getType(): string
    {
        return 'memory';
    }
}
