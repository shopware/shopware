<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Path\Strategy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Path\Strategy\PlainPathStrategy;
use Shopware\Core\Content\Media\Domain\Path\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Domain\Path\Struct\ThumbnailLocationStruct;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Core\Path\Strategy\PlainPathStrategy
 */
class PlainPathStrategyTest extends TestCase
{
    /**
     * @dataProvider strategyProvider
     */
    public function testStrategy(MediaLocationStruct|ThumbnailLocationStruct $struct, string $expected): void
    {
        $strategy = new PlainPathStrategy();

        $generate = $strategy->generate([$struct]);

        static::assertArrayHasKey($struct->id, $generate);

        static::assertSame($expected, $generate[$struct->id]);
    }

    public static function strategyProvider(): \Generator
    {
        yield 'Test without extension' => [
            new MediaLocationStruct('foo', null, 'test', null),
            'media/test',
        ];

        yield 'Test with extension' => [
            new MediaLocationStruct('foo', 'jpg', 'test', null),
            'media/test.jpg',
        ];

        yield 'Test with extension and cache buster' => [
            new MediaLocationStruct('foo', 'jpg', 'test', new \DateTimeImmutable('2021-01-01')),
            'media/1609459200/test.jpg',
        ];

        yield 'Test with thumbnail' => [
            new ThumbnailLocationStruct(
                'foo',
                100,
                100,
                new MediaLocationStruct('foo', 'jpg', 'test', new \DateTimeImmutable('2021-01-01'))
            ),
            'thumbnail/1609459200/test_100x100.jpg',
        ];
    }
}
