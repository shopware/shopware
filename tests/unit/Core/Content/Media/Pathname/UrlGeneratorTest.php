<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Pathname;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\FilenamePathnameStrategy;
use Shopware\Core\Content\Media\Pathname\UrlGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Pathname\UrlGenerator
 */
#[Package('buyers-experience')]
class UrlGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);
    }

    public function testAbsoluteMediaUrl(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign([
            'id' => Uuid::randomHex(),
            'fileName' => 'file.jpg',
        ]);

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $urlGenerator = new UrlGenerator(
            new FilenamePathnameStrategy(),
            $filesystem
        );

        static::assertSame(
            'http://localhost:8000/media/d0/b3/24/file.jpg',
            $urlGenerator->getAbsoluteMediaUrl($mediaEntity)
        );
    }

    public function testMediaUrlWithEmptyRequest(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file.jpg',
            ]
        );
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $urlGenerator = new UrlGenerator(
            new FilenamePathnameStrategy(),
            $filesystem
        );

        static::assertSame(
            'http://localhost:8000/media/d0/b3/24/file.jpg',
            $urlGenerator->getAbsoluteMediaUrl($mediaEntity)
        );
    }

    public function testAbsoluteThumbnailUrl(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file.jpg',
            ]
        );
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->assign(
            [
                'width' => 100,
                'height' => 100,
            ]
        );

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $urlGenerator = new UrlGenerator(
            new FilenamePathnameStrategy(),
            $filesystem
        );

        static::assertSame(
            'http://localhost:8000/thumbnail/d0/b3/24/file.jpg_100x100',
            $urlGenerator->getAbsoluteThumbnailUrl($mediaEntity, $mediaThumbnailEntity)
        );
    }

    public function testRelativeMediaUrl(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file.jpg',
            ]
        );
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $urlGenerator = new UrlGenerator(
            new FilenamePathnameStrategy(),
            $filesystem
        );

        static::assertSame(
            'media/d0/b3/24/file.jpg',
            $urlGenerator->getRelativeMediaUrl($mediaEntity)
        );
    }

    public function testRelativeThumbnailUrl(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file.jpg',
            ]
        );
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->assign(
            [
                'width' => 100,
                'height' => 100,
            ]
        );

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $urlGenerator = new UrlGenerator(
            new FilenamePathnameStrategy(),
            $filesystem
        );

        static::assertSame(
            'thumbnail/d0/b3/24/file.jpg_100x100',
            $urlGenerator->getRelativeThumbnailUrl($mediaEntity, $mediaThumbnailEntity)
        );
    }

    public function testResetUrlGenerator(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file.jpg',
            ]
        );

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $urlGenerator = new UrlGenerator(new FilenamePathnameStrategy(), $filesystem);
        $urlGenerator->getAbsoluteMediaUrl($mediaEntity);
        $urlGeneratorAssert = new UrlGenerator(new FilenamePathnameStrategy(), $filesystem);
        $urlGeneratorAssert->getAbsoluteMediaUrl($mediaEntity);
        $urlGeneratorAssertStaysUntouched = new UrlGenerator(new FilenamePathnameStrategy(), $filesystem);

        // Both $fallbackBaseUrl should be same
        static::assertSame(print_r($urlGeneratorAssert, true), print_r($urlGenerator, true));

        $urlGenerator->reset();

        // Both $fallbackBaseUrl should be same
        static::assertSame(print_r($urlGeneratorAssertStaysUntouched, true), print_r($urlGenerator, true));
    }
}
