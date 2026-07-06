<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailData;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ExternalThumbnailData::class)]
class ExternalThumbnailDataTest extends TestCase
{
    public function testConstructWithValidHttpUrl(): void
    {
        $data = new ExternalThumbnailData(
            url: 'http://localhost:8000/thumb.jpg',
            width: 200,
            height: 200
        );

        static::assertSame('http://localhost:8000/thumb.jpg', $data->url);
        static::assertSame(200, $data->width);
        static::assertSame(200, $data->height);
    }

    public function testConstructWithValidHttpsUrl(): void
    {
        $data = new ExternalThumbnailData(
            url: 'https://localhost:8000/thumb.jpg',
            width: 400,
            height: 300
        );

        static::assertSame('https://localhost:8000/thumb.jpg', $data->url);
        static::assertSame(400, $data->width);
        static::assertSame(300, $data->height);
    }

    public function testConstructThrowsExceptionForInvalidUrl(): void
    {
        $this->expectExceptionObject(MediaException::invalidUrl('invalid-url'));

        new ExternalThumbnailData(
            url: 'invalid-url',
            width: 200,
            height: 200
        );
    }

    public function testConstructThrowsExceptionForFileUrl(): void
    {
        $this->expectExceptionObject(MediaException::invalidUrl('file://test.jpg'));

        new ExternalThumbnailData(
            url: 'file://test.jpg',
            width: 200,
            height: 200
        );
    }

    public function testConstructThrowsExceptionForZeroWidth(): void
    {
        $this->expectExceptionObject(MediaException::invalidDimension('width', 0));

        new ExternalThumbnailData(
            url: 'https://localhost:8000/thumb.jpg',
            width: 0, // @phpstan-ignore argument.type
            height: 200
        );
    }

    public function testConstructThrowsExceptionForNegativeWidth(): void
    {
        $this->expectExceptionObject(MediaException::invalidDimension('width', -100));

        new ExternalThumbnailData(
            url: 'https://localhost:8000/thumb.jpg',
            width: -100, // @phpstan-ignore argument.type
            height: 200
        );
    }

    public function testConstructThrowsExceptionForZeroHeight(): void
    {
        $this->expectExceptionObject(MediaException::invalidDimension('height', 0));

        new ExternalThumbnailData(
            url: 'https://localhost:8000/thumb.jpg',
            width: 200,
            height: 0 // @phpstan-ignore argument.type
        );
    }

    public function testConstructThrowsExceptionForNegativeHeight(): void
    {
        $this->expectExceptionObject(MediaException::invalidDimension('height', -100));

        new ExternalThumbnailData(
            url: 'https://localhost:8000/thumb.jpg',
            width: 200,
            height: -100 // @phpstan-ignore argument.type
        );
    }

    public function testConstructWithLargeValidDimensions(): void
    {
        $data = new ExternalThumbnailData(
            url: 'https://localhost:8000/thumb.jpg',
            width: 4000,
            height: 3000
        );

        static::assertSame(4000, $data->width);
        static::assertSame(3000, $data->height);
    }

    public function testConstructWithUrlContainingQueryParameters(): void
    {
        $url = 'https://localhost:8000/thumb.jpg?width=200&height=200&format=webp';
        $data = new ExternalThumbnailData(
            url: $url,
            width: 200,
            height: 200
        );

        static::assertSame($url, $data->url);
    }
}
