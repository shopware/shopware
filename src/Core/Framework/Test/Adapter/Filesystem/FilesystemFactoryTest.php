<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Filesystem;

use League\Flysystem\Visibility;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;
use Shopware\Core\Framework\Adapter\Filesystem\Exception\AdapterFactoryNotFoundException;
use Shopware\Core\Framework\Adapter\Filesystem\Exception\DuplicateFilesystemFactoryException;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;

/**
 * @internal
 */
class FilesystemFactoryTest extends TestCase
{
    public function testMultipleSame(): void
    {
        static::expectException(DuplicateFilesystemFactoryException::class);
        new FilesystemFactory([new LocalFactory(), new LocalFactory()]);
    }

    public function testCreateLocalAdapter(): void
    {
        $factory = new FilesystemFactory([new LocalFactory()]);
        $adapter = $factory->factory([
            'type' => 'local',
            'config' => [
                'root' => __DIR__,
                'options' => [
                    'visibility' => Visibility::PUBLIC,
                ],
            ],
        ]);

        static::assertSame(Visibility::PUBLIC, $adapter->visibility(''));
    }

    public function testCreateUnknown(): void
    {
        $factory = new FilesystemFactory([new LocalFactory()]);
        static::expectException(AdapterFactoryNotFoundException::class);
        $factory->factory([
            'type' => 'test2',
        ]);
    }
}
