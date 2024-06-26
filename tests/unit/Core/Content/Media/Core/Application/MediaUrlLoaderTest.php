<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Application;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Application\MediaUrlLoader;
use Shopware\Core\Content\Media\Core\Application\RemoteThumbnailLoader;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Test\IdsCollection;

/**
 * @internal
 */
#[CoversClass(MediaUrlLoader::class)]
class MediaUrlLoaderTest extends TestCase
{
    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('loadedProvider')]
    public function testLoad(IdsCollection $ids, PartialEntity $entity, array $expected): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $subscriber = new MediaUrlLoader(
            new MediaUrlGenerator($filesystem),
            $this->createMock(RemoteThumbnailLoader::class)
        );

        $subscriber->loaded([$entity]);

        $actual = [$entity->get('id') => $entity->get('url')];

        if ($entity->has('thumbnails')) {
            if ($entity->get('thumbnails') !== null) {
                static::assertIsIterable($entity->get('thumbnails'));
                foreach ($entity->get('thumbnails') as $thumbnail) {
                    static::assertInstanceOf(Entity::class, $thumbnail);
                    $actual[$thumbnail->get('id')] = $thumbnail->get('url');
                }
            } else {
                $actual[$ids->get('thumbnail')] = null;
            }
        }

        foreach ($expected as $id => $value) {
            static::assertArrayHasKey($id, $actual);
            static::assertEquals($value, $actual[$id]);
        }
    }

    public static function loadedProvider(): \Generator
    {
        $ids = new IdsCollection();
        yield 'Test without updated at' => [
            $ids,
            (new PartialEntity())->assign(['id' => $ids->get('media'), 'path' => '/foo/bar.png', 'private' => false]),
            [$ids->get('media') => 'http://localhost:8000/foo/bar.png'],
        ];

        yield 'Test private will be skipped' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'private' => true,
            ]),
            [$ids->get('media') => ''],
        ];

        yield 'Skip generate when no path set' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'private' => false,
                'url' => 'prefilled',
            ]),
            [$ids->get('media') => 'prefilled'],
        ];

        yield 'Test with updated at' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'updatedAt' => new \DateTimeImmutable('2000-01-01'),
                'private' => false,
            ]),
            [$ids->get('media') => 'http://localhost:8000/foo/bar.png?ts=946684800'],
        ];

        yield 'Test with unset thumbnails' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'private' => false,
                'thumbnails' => null,
            ]),
            [
                $ids->get('media') => 'http://localhost:8000/foo/bar.png',
                $ids->get('thumbnail') => null,
            ],
        ];

        yield 'Test with thumbnails' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'private' => false,
                'thumbnails' => [
                    (new PartialEntity())->assign([
                        'id' => $ids->get('thumbnail'),
                        'path' => '/foo/bar.png',
                    ]),
                ],
            ]),
            [
                $ids->get('media') => 'http://localhost:8000/foo/bar.png',
                $ids->get('thumbnail') => 'http://localhost:8000/foo/bar.png',
            ],
        ];

        yield 'Skip thumbnail when no path set' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'private' => false,
                'thumbnails' => [
                    (new PartialEntity())->assign([
                        'id' => $ids->get('thumbnail'),
                    ]),
                ],
            ]),
            [
                $ids->get('media') => 'http://localhost:8000/foo/bar.png',
                $ids->get('thumbnail') => '',
            ],
        ];

        yield 'Test with thumbnails and updated at' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'updatedAt' => new \DateTimeImmutable('2000-01-01'),
                'private' => false,
                'thumbnails' => [
                    (new PartialEntity())->assign([
                        'id' => $ids->get('thumbnail'),
                        'path' => '/thumb/bar.png',
                        'updatedAt' => new \DateTimeImmutable('2000-01-01'),
                    ]),
                ],
            ]),
            [
                $ids->get('media') => 'http://localhost:8000/foo/bar.png?ts=946684800',
                $ids->get('thumbnail') => 'http://localhost:8000/thumb/bar.png?ts=946684800',
            ],
        ];
    }

    public function testCallRemoteThumbnailLoader(): void
    {
        $ids = new IdsCollection();
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $remoteThumbnailLoader = $this->createMock(RemoteThumbnailLoader::class);

        $subscriber = new MediaUrlLoader(
            new MediaUrlGenerator($filesystem),
            $remoteThumbnailLoader,
            true
        );

        $entity = (new PartialEntity())->assign([
            'id' => $ids->get('media'),
            'path' => 'foo/bar.png',
            'private' => false,
        ]);

        $remoteThumbnailLoader->expects(static::once())->method('load')->with([$entity]);

        $subscriber->loaded([$entity]);
    }
}
