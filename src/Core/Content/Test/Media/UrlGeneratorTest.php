<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\FilenamePathnameStrategy;
use Shopware\Core\Content\Media\Pathname\UrlGenerator;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class UrlGeneratorTest extends TestCase
{
    public function testAbsoluteMediaUrl(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file.jpg',
            ]
        );
        $urlGenerator = new UrlGenerator(new FilenamePathnameStrategy(), new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
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
        $urlGenerator = new UrlGenerator(new FilenamePathnameStrategy(), new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
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

        $urlGenerator = new UrlGenerator(new FilenamePathnameStrategy(), new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
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
        $urlGenerator = new UrlGenerator(new FilenamePathnameStrategy(), new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
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

        $urlGenerator = new UrlGenerator(new FilenamePathnameStrategy(), new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
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

        $fs = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $urlGenerator = new UrlGenerator(new FilenamePathnameStrategy(), $fs);
        $urlGenerator->getAbsoluteMediaUrl($mediaEntity);
        $urlGeneratorAssert = new UrlGenerator(new FilenamePathnameStrategy(), $fs);
        $urlGeneratorAssert->getAbsoluteMediaUrl($mediaEntity);
        $urlGeneratorAssertStaysUntouched = new UrlGenerator(new FilenamePathnameStrategy(), $fs);

        // Both $fallbackBaseUrl should be same
        static::assertSame(print_r($urlGeneratorAssert, true), print_r($urlGenerator, true));

        $urlGenerator->reset();

        // Both $fallbackBaseUrl should be same
        static::assertSame(print_r($urlGeneratorAssertStaysUntouched, true), print_r($urlGenerator, true));
    }
}
