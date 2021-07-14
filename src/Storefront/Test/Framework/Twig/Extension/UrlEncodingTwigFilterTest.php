<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;

class UrlEncodingTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testHappyPath(): void
    {
        $filter = new UrlEncodingTwigFilter();
        $url = 'https://shopware.com:80/some/thing';
        static::assertEquals($url, $filter->encodeUrl($url));
    }

    public function testReturnsNullsIfNoUrlIsGiven(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertNull($filter->encodeUrl(null));
    }

    public function testItEncodesWithoutPort(): void
    {
        $filter = new UrlEncodingTwigFilter();
        $url = 'https://shopware.com/some/thing';
        static::assertEquals($url, $filter->encodeUrl($url));
    }

    public function testRespectsQueryParameter(): void
    {
        $filter = new UrlEncodingTwigFilter();
        $url = 'https://shopware.com/some/thing?a=3&b=25';
        static::assertEquals($url, $filter->encodeUrl($url));
    }

    public function testReturnsEncodedPathsWithoutHostAndScheme(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertEquals(
            'shopware.com/some/thing',
            $filter->encodeUrl('shopware.com/some/thing')
        );
    }

    public function testItEncodesSpaces(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertEquals(
            'https://shopware.com:80/so%20me/thing%20new.jpg',
            $filter->encodeUrl('https://shopware.com:80/so me/thing new.jpg')
        );
    }

    public function testItEncodesSpecialCharacters(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertEquals(
            'https://shopware.com:80/so%20me/thing%20new.jpg',
            $filter->encodeUrl('https://shopware.com:80/so me/thing new.jpg')
        );
    }

    public function testItReturnsNullIfMediaIsNull(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertNull($filter->encodeMediaUrl(null));
    }

    public function testNullIfNoMediaIsUploaded(): void
    {
        $filter = new UrlEncodingTwigFilter();
        $media = new MediaEntity();

        static::assertNull($filter->encodeMediaUrl($media));
    }

    public function testItEncodesTheUrl(): void
    {
        static::markTestSkipped('Flaky');

        $filter = new UrlEncodingTwigFilter();
        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $uploadTime = new \DateTime();
        $utc = $uploadTime->getTimestamp();

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setMimeType('image/png');
        $media->setFileExtension('png');
        $media->setUploadedAt($uploadTime);
        $media->setFileName('(image with spaces and brackets)');
        $media->setUrl($urlGenerator->getAbsoluteMediaUrl($media));

        static::assertStringEndsWith("{$utc}/%28image%20with%20spaces%20and%20brackets%29.png", $filter->encodeMediaUrl($media));
    }
}
