<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Infrastructure\Path;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Content\Media\Core\Params\UrlParamsSource;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Content\Media\MediaException;

/**
 * @internal
 */
#[CoversClass(MediaUrlGenerator::class)]
#[CoversClass(AbstractMediaUrlGenerator::class)]
class MediaUrlGeneratorTest extends TestCase
{
    #[DataProvider('generateProvider')]
    public function testGenerate(UrlParams $params, ?string $expected): void
    {
        $generator = new MediaUrlGenerator(
            new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']),
        );

        if ($expected === null) {
            $this->expectException(MediaException::class);
        }

        $url = $generator->generate([$params]);

        static::assertSame([$expected], $url);
    }

    public static function generateProvider(): \Generator
    {
        yield 'Test with empty path' => [
            new UrlParams('id', UrlParamsSource::MEDIA, '', null),
            'http://localhost:8000/',
        ];

        yield 'Test with path' => [
            new UrlParams('id', UrlParamsSource::MEDIA, 'test.jpg', null),
            'http://localhost:8000/test.jpg',
        ];

        yield 'Test with longer path' => [
            new UrlParams('id', UrlParamsSource::MEDIA, 'media/foo/3a/test.jpg', null),
            'http://localhost:8000/media/foo/3a/test.jpg',
        ];

        yield 'Test with date' => [
            new UrlParams('id', UrlParamsSource::MEDIA, 'test.jpg', new \DateTimeImmutable('2021-01-01')),
            'http://localhost:8000/test.jpg?1609459200',
        ];
    }
}
