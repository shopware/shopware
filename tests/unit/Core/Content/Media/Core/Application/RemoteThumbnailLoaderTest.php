<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Application;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Core\Application\RemoteThumbnailLoader;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

/**
 * @internal
 */
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

        $prefixFilesystem = $this->createMock(PrefixFilesystem::class);
        $prefixFilesystem->method('publicUrl')->willReturn('http://localhost:8000');

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($thumbnailSizes);

        $loader = new RemoteThumbnailLoader(
            new MediaUrlGenerator($filesystem),
            $connection,
            $prefixFilesystem,
            '{mediaUrl}/{mediaPath}?width={width}'
        );

        $loader->load([$entity]);

        $actual = [$entity->get('id') => $entity->get('url')];

        static::assertArrayHasKey($ids->get('media'), $actual);
        static::assertEquals($expected['media'], $actual[$ids->get('media')]);

        if (\count($thumbnailSizes) > 0) {
            static::assertIsIterable($entity->get('thumbnails'));

            foreach ($entity->get('thumbnails') as $thumbnail) {
                static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnail);
                static::assertTrue(\in_array($thumbnail->get('url'), $expected['thumbnails'], true));
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
                ['media_folder_id' => $ids->get('mediaFolderId'), 'width' => '200', 'height' => '200'],
                ['media_folder_id' => $ids->get('mediaFolderId'), 'width' => '400', 'height' => '400'],
                ['media_folder_id' => $ids->get('mediaFolderId'), 'width' => '600', 'height' => '600'],
            ],
            [
                'media' => 'http://localhost:8000/foo/bar.png',
                'thumbnails' => [
                    'http://localhost:8000/foo/bar.png?width=200',
                    'http://localhost:8000/foo/bar.png?width=400',
                    'http://localhost:8000/foo/bar.png?width=600',
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
                ['media_folder_id' => $ids->get('mediaFolderId'), 'width' => '200', 'height' => '200'],
                ['media_folder_id' => $ids->get('mediaFolderId'), 'width' => '400', 'height' => '400'],
                ['media_folder_id' => $ids->get('mediaFolderId'), 'width' => '600', 'height' => '600'],
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
    }

    public function testReset(): void
    {
        $ids = new IdsCollection();
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $thumbnailSizes = [
            ['media_folder_id' => $ids->get('mediaFolderId'), 'width' => '200', 'height' => '200'],
            ['media_folder_id' => $ids->get('mediaFolderId'), 'width' => '400', 'height' => '400'],
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($thumbnailSizes);

        $entity = (new PartialEntity())->assign([
            'id' => $ids->get('media'),
            'path' => 'foo/bar.png',
            'mediaFolderId' => $ids->get('mediaFolderId'),
            'private' => false,
        ]);

        $loader = new RemoteThumbnailLoader(
            new MediaUrlGenerator($filesystem),
            $connection,
            $this->createMock(PrefixFilesystem::class),
            '{mediaUrl}/{mediaPath}?width={width}'
        );

        $loader->load([$entity]);
        static::assertNotEmpty(ReflectionHelper::getPropertyValue($loader, 'mediaFolderThumbnailSizes'));

        $loader->reset();
        static::assertEmpty(ReflectionHelper::getPropertyValue($loader, 'mediaFolderThumbnailSizes'));
    }
}
