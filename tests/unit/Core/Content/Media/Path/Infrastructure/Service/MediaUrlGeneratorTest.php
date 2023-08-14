<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Path\Infrastructure\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Path\Infrastructure\Service\MediaUrlGenerator;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Path\Infrastructure\Service\MediaUrlGenerator
 */
class MediaUrlGeneratorTest extends TestCase
{
    /**
     * @param array{path:string, updatedAt?: \DateTimeInterface|null} $params
     *
     * @dataProvider generateProvider
     */
    public function testGenerate(array $params, ?string $expected): void
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
            ['path' => ''],
            'http://localhost:8000/',
        ];

        yield 'Test with null path' => [
            ['path' => null],
            null,
        ];

        yield 'Test with path' => [
            ['path' => 'test.jpg'],
            'http://localhost:8000/test.jpg',
        ];

        yield 'Test with longer path' => [
            ['path' => 'media/foo/3a/test.jpg'],
            'http://localhost:8000/media/foo/3a/test.jpg',
        ];

        yield 'Test with null date' => [
            ['path' => 'test.jpg', 'createdAt' => null],
            'http://localhost:8000/test.jpg',
        ];

        yield 'Test with date' => [
            ['path' => 'test.jpg', 'updatedAt' => new \DateTimeImmutable('2021-01-01')],
            'http://localhost:8000/test.jpg?1609459200',
        ];
    }
}
