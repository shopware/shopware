<?php declare(strict_types=1);

namespace src\Core\Content\Test\Media;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\PathGenerator;
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
                'fileName' => 'file',
                'fileExtension' => 'jpg',
            ]
        );

        $pathGenerator = new PathGenerator(new FilenamePathnameStrategy());
        $urlGenerator = new UrlGenerator($pathGenerator, new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
        static::assertSame(
            'http://localhost:8000/media/8c/7d/d9/file.jpg',
            $urlGenerator->getAbsoluteMediaUrl($mediaEntity)
        );
    }

    public function testMediaUrlWithEmptyRequest(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file',
                'fileExtension' => 'jpg',
            ]
        );

        $pathGenerator = new PathGenerator(new FilenamePathnameStrategy());
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $urlGenerator = new UrlGenerator($pathGenerator, new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
        static::assertSame(
            'http://localhost:8000/media/8c/7d/d9/file.jpg',
            $urlGenerator->getAbsoluteMediaUrl($mediaEntity)
        );
    }

    public function testAbsoluteThumbnailUrl(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file',
                'fileExtension' => 'jpg',
            ]
        );
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->assign(
            [
                'width' => 100,
                'height' => 100,
            ]
        );

        $pathGenerator = new PathGenerator(new FilenamePathnameStrategy());
        $urlGenerator = new UrlGenerator($pathGenerator, new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
        static::assertSame(
            'http://localhost:8000/thumbnail/8c/7d/d9/file_100x100.jpg',
            $urlGenerator->getAbsoluteThumbnailUrl($mediaEntity, $mediaThumbnailEntity)
        );
    }

    public function testRelativeMediaUrl(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file',
                'fileExtension' => 'jpg',
            ]
        );

        $pathGenerator = new PathGenerator(new FilenamePathnameStrategy());
        $urlGenerator = new UrlGenerator($pathGenerator, new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
        static::assertSame(
            'media/8c/7d/d9/file.jpg',
            $urlGenerator->getRelativeMediaUrl($mediaEntity)
        );
    }

    public function testRelativeThumbnailUrl(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file',
                'fileExtension' => 'jpg',
            ]
        );
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->assign(
            [
                'width' => 100,
                'height' => 100,
            ]
        );

        $pathGenerator = new PathGenerator(new FilenamePathnameStrategy());
        $urlGenerator = new UrlGenerator($pathGenerator, new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']));
        static::assertSame(
            'thumbnail/8c/7d/d9/file_100x100.jpg',
            $urlGenerator->getRelativeThumbnailUrl($mediaEntity, $mediaThumbnailEntity)
        );
    }

    public function testResetUrlGenerator(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file',
                'fileExtension' => 'jpg',
                'path' => 'my/pa/th/file.jpg',
            ]
        );

        $fs = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $pathGenerator = new PathGenerator(new FilenamePathnameStrategy());
        $urlGenerator = new UrlGenerator($pathGenerator, $fs);
        $urlGenerator->getAbsoluteMediaUrl($mediaEntity);
        $urlGeneratorAssert = new UrlGenerator($pathGenerator, $fs);
        $urlGeneratorAssert->getAbsoluteMediaUrl($mediaEntity);
        $urlGeneratorAssertStaysUntouched = new UrlGenerator($pathGenerator, $fs);

        // Both $fallbackBaseUrl should be same
        static::assertSame(print_r($urlGeneratorAssert, true), print_r($urlGenerator, true));

        $urlGenerator->reset();

        // Both $fallbackBaseUrl should be same
        static::assertSame(print_r($urlGeneratorAssertStaysUntouched, true), print_r($urlGenerator, true));
    }
}
