<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LocalFactory::class)]
class LocalFactoryTest extends TestCase
{
    public function testFactory(): void
    {
        $factory = new LocalFactory();
        static::assertSame('local', $factory->getType());

        $adapter = $factory->create([
            'root' => __DIR__,
        ]);

        static::assertInstanceOf(LocalFilesystemAdapter::class, $adapter);
    }

    public function testCreateFilesystemUsesThePublicUrlOption(): void
    {
        $filesystem = (new LocalFactory())->createFilesystem(['root' => __DIR__], ['public_url' => 'https://cdn.example']);

        static::assertSame('https://cdn.example/media/a.jpg', $filesystem->publicUrl('media/a.jpg'));
    }

    public function testPublicUrlGeneratorTakesPrecedenceOverThePublicUrlOption(): void
    {
        $generator = new class implements PublicUrlGenerator {
            public function publicUrl(string $path, Config $config): string
            {
                return 'https://generated.example/' . $path;
            }
        };

        $filesystem = (new LocalFactory())->createFilesystemWithPublicUrlGenerator(
            ['root' => __DIR__],
            ['public_url' => 'https://cdn.example'],
            $generator,
        );

        static::assertSame('https://generated.example/media/a.jpg', $filesystem->publicUrl('media/a.jpg'));
    }
}
