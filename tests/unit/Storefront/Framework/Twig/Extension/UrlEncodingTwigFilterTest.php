<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Twig\TwigFilter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UrlEncodingTwigFilter::class)]
class UrlEncodingTwigFilterTest extends TestCase
{
    private UrlEncodingTwigFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new UrlEncodingTwigFilter();
    }

    public function testGetFiltersReturnsCorrectFilters(): void
    {
        $filters = $this->filter->getFilters();

        static::assertCount(2, $filters);
        static::assertContainsOnlyInstancesOf(TwigFilter::class, $filters);

        $filterNames = array_map(static fn (TwigFilter $filter) => $filter->getName(), $filters);
        static::assertContains('sw_encode_url', $filterNames);
        static::assertContains('sw_encode_media_url', $filterNames);
    }

    public function testEncodeUrlDelegatesToUrlEncoder(): void
    {
        $url = 'https://shopware.com/some/thing';
        static::assertSame($url, $this->filter->encodeUrl($url));
    }

    public function testEncodeUrlReturnsNullForNullInput(): void
    {
        static::assertNull($this->filter->encodeUrl(null));
    }

    public function testEncodeUrlHandlesSpecialCharacters(): void
    {
        static::assertSame(
            'https://shopware.com:80/so%20me/thing%20new.jpg',
            $this->filter->encodeUrl('https://shopware.com:80/so me/thing new.jpg')
        );
    }

    public function testEncodeMediaUrlReturnsNullIfMediaIsNull(): void
    {
        static::assertNull($this->filter->encodeMediaUrl(null));
    }

    public function testEncodeMediaUrlReturnsNullIfNoMediaIsUploaded(): void
    {
        $media = new MediaEntity();
        static::assertNull($this->filter->encodeMediaUrl($media));
    }

    public function testEncodeMediaUrlWithSpacesInUrl(): void
    {
        $media = new MediaEntity();
        $media->setUrl('https://example.com/media/file with spaces.jpg');
        $media->setFileName('file with spaces.jpg');
        $media->setFileExtension('jpg');
        $media->setMimeType('image/jpeg');

        $result = $this->filter->encodeMediaUrl($media);

        static::assertIsString($result);
        static::assertStringContainsString('example.com', $result);
        static::assertStringContainsString('file', $result);
        static::assertStringContainsString('spaces', $result);
    }

    public function testEncodeMediaUrlWithComplexMediaEntity(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $urlGenerator = new MediaUrlGenerator($filesystem);
        $uploadTime = new \DateTime();

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setMimeType('image/png');
        $media->setFileExtension('png');
        $media->setUploadedAt($uploadTime);
        $media->setFileName('(image with spaces and brackets)');
        $media->setPath('(image with spaces and brackets).png');

        $urls = $urlGenerator->generate(['foo' => UrlParams::fromMedia($media)]);

        static::assertArrayHasKey('foo', $urls);
        $url = $urls['foo'];

        $media->setUrl((string) $url);

        $result = $this->filter->encodeMediaUrl($media);

        static::assertIsString($result);
        static::assertStringEndsWith('%28image%20with%20spaces%20and%20brackets%29.png', $result);
    }
}
