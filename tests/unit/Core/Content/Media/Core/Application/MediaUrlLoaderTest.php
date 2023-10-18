<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Application;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Core\Application\MediaUrlLoader;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Core\Application\MediaUrlLoader
 */
class MediaUrlLoaderTest extends TestCase
{
    /**
     * @dataProvider loadedProvider
     *
     * @param array<string, string> $expected
     */
    public function testLoad(IdsCollection $ids, PartialEntity $entity, array $expected): void
    {
        Feature::skipTestIfInActive('v6.6.0.0', $this);

        $mock = $this->createMock(UrlGeneratorInterface::class);
        $mock->expects(static::never())
            ->method('getAbsoluteMediaUrl');

        $mock->expects(static::never())
            ->method('getRelativeMediaUrl');

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $subscriber = new MediaUrlLoader(new MediaUrlGenerator($filesystem), $mock);

        $subscriber->loaded([$entity]);

        $actual = [$entity->get('id') => $entity->get('url')];

        if ($entity->has('thumbnails')) {
            static::assertIsIterable($entity->get('thumbnails'));
            foreach ($entity->get('thumbnails') as $thumbnail) {
                static::assertInstanceOf(Entity::class, $thumbnail);
                $actual[$thumbnail->get('id')] = $thumbnail->get('url');
            }
        }

        foreach ($expected as $id => $value) {
            static::assertArrayHasKey($id, $actual);
            static::assertEquals($value, $actual[$id]);
        }
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0", "media_path"})
     */
    public function testLegacy(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $mock = $this->createMock(UrlGeneratorInterface::class);

        $mock->expects(static::once())
            ->method('getAbsoluteMediaUrl')
            ->willReturn('http://localhost:8000/foo/bar.png');

        $mock->expects(static::once())
            ->method('getAbsoluteThumbnailUrl')
            ->willReturn('http://localhost:8000/foo/thumb.png');

        $media = new MediaEntity();

        $thumbnail = (new MediaThumbnailEntity())->assign([
            'id' => 'thumbnail',
            'width' => 100,
            'height' => 100,
        ]);

        $media->assign([
            'id' => 'media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
            'private' => false,
            'fileName' => 'bar',
            'thumbnails' => new MediaThumbnailCollection([
                $thumbnail,
            ]),
        ]);

        $new = $this->createMock(MediaUrlGenerator::class);

        $subscriber = new MediaUrlLoader($new, $mock);

        $subscriber->legacy([$media]);

        static::assertEquals('http://localhost:8000/foo/bar.png', $media->getUrl());
        static::assertEquals('http://localhost:8000/foo/thumb.png', $thumbnail->getUrl());
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0", "media_path"})
     */
    public function testLegacyPath(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $mock = $this->createMock(UrlGeneratorInterface::class);

        $mock->expects(static::once())
            ->method('getRelativeMediaUrl')
            ->willReturn('/foo/bar.png');

        $mock->expects(static::once())
            ->method('getRelativeThumbnailUrl')
            ->willReturn('/foo/thumb.png');

        $media = new MediaEntity();

        $thumbnail = (new MediaThumbnailEntity())->assign([
            'id' => 'thumbnail',
            'width' => 100,
            'height' => 100,
        ]);

        $media->assign([
            'id' => 'media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
            'private' => false,
            'fileName' => 'bar',
            'thumbnails' => new MediaThumbnailCollection([
                $thumbnail,
            ]),
        ]);

        $new = $this->createMock(MediaUrlGenerator::class);

        $subscriber = new MediaUrlLoader($new, $mock);

        $subscriber->legacyPath([$media]);

        static::assertEquals('/foo/bar.png', $media->getPath());
        static::assertEquals('/foo/thumb.png', $thumbnail->getPath());
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0", "media_path"})
     */
    public function testLegacyPathForPrivate(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $mock = $this->createMock(UrlGeneratorInterface::class);

        $mock->expects(static::once())
            ->method('getRelativeMediaUrl')
            ->willReturn('/foo/bar.png');

        $mock->expects(static::once())
            ->method('getRelativeThumbnailUrl')
            ->willReturn('/foo/thumb.png');

        $media = new MediaEntity();

        $thumbnail = (new MediaThumbnailEntity())->assign([
            'id' => 'thumbnail',
            'width' => 100,
            'height' => 100,
        ]);

        $media->assign([
            'id' => 'media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
            'private' => true,
            'fileName' => 'bar',
            'thumbnails' => new MediaThumbnailCollection([
                $thumbnail,
            ]),
        ]);

        $new = $this->createMock(MediaUrlGenerator::class);

        $subscriber = new MediaUrlLoader($new, $mock);

        $subscriber->legacyPath([$media]);

        static::assertEquals('/foo/bar.png', $media->getPath());
        static::assertEquals('/foo/thumb.png', $thumbnail->getPath());
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0", "media_path"})
     */
    public function testLegacySkipped(): void
    {
        $mock = $this->createMock(UrlGeneratorInterface::class);
        $mock->expects(static::never())
            ->method('getRelativeMediaUrl');

        $media = new MediaEntity();
        $media->assign([
            'id' => 'media',
            'path' => 'media/foo.png',
        ]);

        $new = $this->createMock(MediaUrlGenerator::class);

        $subscriber = new MediaUrlLoader($new, $mock);
        $subscriber->legacyPath([$media]);

        static::assertEquals('media/foo.png', $media->getPath());
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0", "media_path"})
     */
    public function testLegacyPathWithoutFileName(): void
    {
        $mock = $this->createMock(UrlGeneratorInterface::class);
        $mock->expects(static::never())
            ->method('getRelativeMediaUrl');

        $media = new MediaEntity();
        $media->assign([
            'id' => 'media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
            'path' => '',
        ]);

        $new = $this->createMock(MediaUrlGenerator::class);

        $subscriber = new MediaUrlLoader($new, $mock);
        $subscriber->legacyPath([$media]);

        static::assertEmpty($media->getPath());
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0", "media_path"})
     */
    public function testLegacyFunctionWithoutFilename(): void
    {
        $mock = $this->createMock(UrlGeneratorInterface::class);
        $mock->expects(static::never())
            ->method('getAbsoluteMediaUrl');

        $media = new MediaEntity();
        $media->assign([
            'id' => 'media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
            'path' => '',
        ]);

        $new = $this->createMock(MediaUrlGenerator::class);

        $subscriber = new MediaUrlLoader($new, $mock);
        $subscriber->legacy([$media]);

        static::assertEmpty($media->getUrl());
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
            [$ids->get('media') => 'http://localhost:8000/foo/bar.png?946684800'],
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
                $ids->get('media') => 'http://localhost:8000/foo/bar.png?946684800',
                $ids->get('thumbnail') => 'http://localhost:8000/thumb/bar.png?946684800',
            ],
        ];
    }
}
