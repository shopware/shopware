<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Application;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Core\Application\RemoteThumbnailLoader;
use Shopware\Core\Content\Media\Extension\ResolveRemoteThumbnailUrlExtension;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(RemoteThumbnailLoader::class)]
class RemoteThumbnailLoaderTest extends TestCase
{
    /**
     * @param array<array<string, string>> $thumbnailSizes
     * @param array{media: string, thumbnails: array<string>} $expected
     */
    #[DataProvider('loadProvider')]
    public function testLoad(IdsCollection $ids, PartialEntity $entity, array $thumbnailSizes, array $expected): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $prefixFilesystem = static::createStub(PrefixFilesystem::class);
        $prefixFilesystem->method('publicUrl')->willReturn('http://localhost:8000');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            $thumbnailSizes,
            [[
                'folder_id' => $ids->get('mediaFolderId'),
                'configuration_id' => $ids->get('mediaFolderConfigurationId'),
                'create_thumbnails' => '1',
            ]]
        );

        $dispatcher = new EventDispatcher();
        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        $loader = new RemoteThumbnailLoader(
            new MediaUrlGenerator($filesystem),
            $connection,
            $prefixFilesystem,
            $extensionDispatcher,
            '{mediaUrl}/{mediaPath}?width={width}&ts={mediaUpdatedAt}'
        );

        $loader->load([$entity]);

        $actual = [$entity->get('id') => $entity->get('url')];

        static::assertArrayHasKey($ids->get('media'), $actual);
        static::assertSame($expected['media'], $actual[$ids->get('media')]);

        if ($thumbnailSizes !== []) {
            static::assertIsIterable($entity->get('thumbnails'));

            foreach ($entity->get('thumbnails') as $thumbnail) {
                static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnail);
                static::assertTrue(\in_array($thumbnail->get('url'), $expected['thumbnails'], true));
                static::assertSame($ids->get('media'), $thumbnail->getMediaId());
            }
        }
    }

    public static function loadProvider(): \Generator
    {
        $ids = new IdsCollection();
        yield 'Test without updated at' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => 'foo/bar.png',
                'mediaFolderId' => $ids->get('mediaFolderId'),
                'private' => false,
            ]),
            [
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '200', 'height' => '200'],
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '400', 'height' => '400'],
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '600', 'height' => '600'],
            ],
            [
                'media' => 'http://localhost:8000/foo/bar.png',
                'thumbnails' => [
                    'http://localhost:8000/foo/bar.png?width=200&ts=',
                    'http://localhost:8000/foo/bar.png?width=400&ts=',
                    'http://localhost:8000/foo/bar.png?width=600&ts=',
                ],
            ],
        ];

        yield 'Test with updated at' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => 'foo/bar.png',
                'mediaFolderId' => $ids->get('mediaFolderId'),
                'updatedAt' => new \DateTimeImmutable('2000-01-01'),
                'private' => false,
            ]),
            [
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '200', 'height' => '200'],
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '400', 'height' => '400'],
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '600', 'height' => '600'],
            ],
            [
                'media' => 'http://localhost:8000/foo/bar.png?ts=946684800',
                'thumbnails' => [
                    'http://localhost:8000/foo/bar.png?width=200&ts=946684800',
                    'http://localhost:8000/foo/bar.png?width=400&ts=946684800',
                    'http://localhost:8000/foo/bar.png?width=600&ts=946684800',
                ],
            ],
        ];

        yield 'Test without thumbnail sizes' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => 'foo/bar.png',
                'mediaFolderId' => $ids->get('mediaFolderId'),
                'private' => false,
            ]),
            [],
            [
                'media' => 'http://localhost:8000/foo/bar.png',
                'thumbnails' => [],
            ],
        ];

        yield 'Test with media path is an external url' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => 'https://test.com/photo/flower.jpg',
                'mediaFolderId' => $ids->get('mediaFolderId'),
                'updatedAt' => new \DateTimeImmutable('2000-01-01'),
                'private' => false,
            ]),
            [
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '200', 'height' => '200'],
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '400', 'height' => '400'],
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '600', 'height' => '600'],
            ],
            [
                'media' => 'https://test.com/photo/flower.jpg?ts=946684800',
                'thumbnails' => [
                    'https://test.com/photo/flower.jpg?width=200&ts=946684800',
                    'https://test.com/photo/flower.jpg?width=400&ts=946684800',
                    'https://test.com/photo/flower.jpg?width=600&ts=946684800',
                ],
            ],
        ];
    }

    /**
     * @param list<array{configuration_id: string, media_thumbnail_size_id: string, width: string, height: string}> $configuredSizes
     * @param list<array{folder_id: string, configuration_id: string, create_thumbnails: string}> $folderConfigurations
     * @param list<array{width: int, height: int}> $fallbackSizes
     * @param list<array{url: string, width: int, height: int}> $expectedThumbnails
     */
    #[DataProvider('fallbackThumbnailProvider')]
    public function testLoadFallbackThumbnails(
        IdsCollection $ids,
        array $configuredSizes,
        array $folderConfigurations,
        array $fallbackSizes,
        array $expectedThumbnails,
        bool $shouldLookupFolders
    ): void {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $connection = static::createMock(Connection::class);
        $connection->expects($shouldLookupFolders ? $this->exactly(2) : $this->once())
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($configuredSizes, $folderConfigurations);

        $prefixFilesystem = static::createStub(PrefixFilesystem::class);
        $prefixFilesystem->method('publicUrl')->willReturn('http://localhost:8000');

        $loader = new RemoteThumbnailLoader(
            new MediaUrlGenerator($filesystem),
            $connection,
            $prefixFilesystem,
            new ExtensionDispatcher(new EventDispatcher()),
            '{mediaUrl}/{mediaPath}?width={width}&height={height}',
            $fallbackSizes
        );

        $entity = (new PartialEntity())->assign([
            'id' => $ids->get('media'),
            'path' => 'foo/bar.png',
            'mediaFolderId' => $ids->get('mediaFolderId'),
            'private' => false,
        ]);

        $loader->load([$entity]);

        static::assertSame('http://localhost:8000/foo/bar.png', $entity->get('url'));
        $thumbnails = \array_values(\iterator_to_array($entity->get('thumbnails')));
        static::assertCount(\count($expectedThumbnails), $thumbnails);

        foreach ($expectedThumbnails as $index => $expectedThumbnail) {
            static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnails[$index]);
            static::assertSame($expectedThumbnail['url'], $thumbnails[$index]->getUrl());
            static::assertSame($expectedThumbnail['width'], $thumbnails[$index]->getWidth());
            static::assertSame($expectedThumbnail['height'], $thumbnails[$index]->getHeight());
        }
    }

    public static function fallbackThumbnailProvider(): \Generator
    {
        $ids = new IdsCollection();
        $fallbackSizes = [
            ['width' => 320, 'height' => 180],
            ['width' => 640, 'height' => 360],
        ];

        yield 'enabled folder without configured sizes uses fallback sizes' => [
            $ids,
            [],
            [[
                'folder_id' => $ids->get('mediaFolderId'),
                'configuration_id' => $ids->get('mediaFolderConfigurationId'),
                'create_thumbnails' => '1',
            ]],
            $fallbackSizes,
            [
                ['url' => 'http://localhost:8000/foo/bar.png?width=320&height=180', 'width' => 320, 'height' => 180],
                ['url' => 'http://localhost:8000/foo/bar.png?width=640&height=360', 'width' => 640, 'height' => 360],
            ],
            true,
        ];

        yield 'enabled folder uses configured sizes instead of fallback sizes' => [
            $ids,
            [
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '200', 'height' => '100'],
            ],
            [[
                'folder_id' => $ids->get('mediaFolderId'),
                'configuration_id' => $ids->get('mediaFolderConfigurationId'),
                'create_thumbnails' => '1',
            ]],
            $fallbackSizes,
            [
                ['url' => 'http://localhost:8000/foo/bar.png?width=200&height=100', 'width' => 200, 'height' => 100],
            ],
            true,
        ];

        yield 'disabled folder does not use configured or fallback sizes' => [
            $ids,
            [
                ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '200', 'height' => '100'],
            ],
            [[
                'folder_id' => $ids->get('mediaFolderId'),
                'configuration_id' => $ids->get('mediaFolderConfigurationId'),
                'create_thumbnails' => '0',
            ]],
            $fallbackSizes,
            [],
            true,
        ];

        yield 'unknown folder has no fallback thumbnails' => [
            $ids,
            [],
            [],
            $fallbackSizes,
            [],
            true,
        ];

        yield 'enabled folder with empty fallback has no thumbnails' => [
            $ids,
            [],
            [[
                'folder_id' => $ids->get('mediaFolderId'),
                'configuration_id' => $ids->get('mediaFolderConfigurationId'),
                'create_thumbnails' => '1',
            ]],
            [],
            [],
            false,
        ];
    }

    public function testFallbackThumbnailSizeIdIsStableAfterReset(): void
    {
        $ids = new IdsCollection();
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $folderConfiguration = [[
            'folder_id' => $ids->get('mediaFolderId'),
            'configuration_id' => $ids->get('mediaFolderConfigurationId'),
            'create_thumbnails' => '1',
        ]];

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls([], $folderConfiguration, [], $folderConfiguration);

        $prefixFilesystem = static::createStub(PrefixFilesystem::class);
        $prefixFilesystem->method('publicUrl')->willReturn('http://localhost:8000');

        $loader = new RemoteThumbnailLoader(
            new MediaUrlGenerator($filesystem),
            $connection,
            $prefixFilesystem,
            new ExtensionDispatcher(new EventDispatcher()),
            '{mediaUrl}/{mediaPath}?width={width}&height={height}',
            [['width' => 320, 'height' => 180]]
        );

        $entity = (new PartialEntity())->assign([
            'id' => $ids->get('media'),
            'path' => 'foo/bar.png',
            'mediaFolderId' => $ids->get('mediaFolderId'),
            'private' => false,
        ]);

        $loader->load([$entity]);
        $thumbnails = $entity->get('thumbnails');
        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnails);
        $thumbnail = $thumbnails->first();
        static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnail);

        $loader->reset();
        $loader->load([$entity]);
        $thumbnailsAfterReset = $entity->get('thumbnails');
        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnailsAfterReset);
        $thumbnailAfterReset = $thumbnailsAfterReset->first();
        static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnailAfterReset);

        static::assertSame($thumbnail->getMediaThumbnailSizeId(), $thumbnailAfterReset->getMediaThumbnailSizeId());
    }

    public function testReset(): void
    {
        $ids = new IdsCollection();
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $thumbnailSizes = [
            ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '200', 'height' => '200'],
            ['configuration_id' => $ids->get('mediaFolderConfigurationId'), 'media_thumbnail_size_id' => $ids->get('mediaThumbnailSizeId'), 'width' => '400', 'height' => '400'],
        ];

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls($thumbnailSizes, [[
            'folder_id' => $ids->get('mediaFolderId'),
            'configuration_id' => $ids->get('mediaFolderConfigurationId'),
            'create_thumbnails' => '1',
        ]]);

        $entity = (new PartialEntity())->assign([
            'id' => $ids->get('media'),
            'path' => 'foo/bar.png',
            'mediaFolderId' => $ids->get('mediaFolderId'),
            'private' => false,
        ]);

        $dispatcher = new EventDispatcher();
        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        $loader = new RemoteThumbnailLoader(
            new MediaUrlGenerator($filesystem),
            $connection,
            static::createStub(PrefixFilesystem::class),
            $extensionDispatcher,
            '{mediaUrl}/{mediaPath}?width={width}&ts={mediaUpdatedAt}'
        );

        $loader->load([$entity]);
        static::assertNotEmpty((new \ReflectionProperty(RemoteThumbnailLoader::class, 'mediaFolderThumbnailSizes'))->getValue($loader));

        $loader->reset();
        static::assertEmpty((new \ReflectionProperty(RemoteThumbnailLoader::class, 'mediaFolderThumbnailSizes'))->getValue($loader));
    }

    public function testExtensionSkipThumbnail(): void
    {
        $ids = new IdsCollection();
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls([], [[
            'folder_id' => $ids->get('mediaFolderId'),
            'configuration_id' => $ids->get('mediaFolderConfigurationId'),
            'create_thumbnails' => '1',
        ]]);

        $entity = (new PartialEntity())->assign([
            'id' => $ids->get('media'),
            'path' => 'foo/bar.png',
            'mediaFolderId' => $ids->get('mediaFolderId'),
            'private' => false,
        ]);

        $dispatcher = new EventDispatcher();
        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        $prefixFilesystem = static::createStub(PrefixFilesystem::class);
        $prefixFilesystem->method('publicUrl')->willReturn('http://localhost:8000');

        $loader = new RemoteThumbnailLoader(
            new MediaUrlGenerator($filesystem),
            $connection,
            $prefixFilesystem,
            $extensionDispatcher,
            '{mediaUrl}/{mediaPath}?width={width}&ts={mediaUpdatedAt}',
            [
                ['width' => 200, 'height' => 200],
                ['width' => 400, 'height' => 400],
            ]
        );

        $dispatcher->addListener(
            ResolveRemoteThumbnailUrlExtension::NAME . '.pre',
            static function (ResolveRemoteThumbnailUrlExtension $event): void {
                if ($event->width === '400') {
                    $event->result = null;
                    $event->stopPropagation();
                }
            }
        );

        $loader->load([$entity]);

        $thumbnails = $entity->get('thumbnails');
        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnails);
        static::assertCount(1, $thumbnails);

        $thumbnail = $thumbnails->first();
        static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnail);
        static::assertSame('http://localhost:8000/foo/bar.png?width=200&ts=', $thumbnail->getUrl());
    }
}
