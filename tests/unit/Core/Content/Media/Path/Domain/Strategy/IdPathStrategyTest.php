<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Path\Domain\Strategy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Path\Domain\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Path\Domain\Struct\ThumbnailLocationStruct;
use Shopware\Core\Content\Media\Path\Implementation\Strategy\IdPathStrategy;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Path\Implementation\Strategy\IdPathStrategy
 */
class IdPathStrategyTest extends TestCase
{
    /**
     * @dataProvider strategyProvider
     */
    public function testStrategy(MediaLocationStruct|ThumbnailLocationStruct $struct, string $expected): void
    {
        $strategy = new IdPathStrategy();

        $generate = $strategy->generate([$struct]);

        static::assertArrayHasKey($struct->id, $generate);

        static::assertSame($expected, $generate[$struct->id]);
    }

    public static function strategyProvider(): \Generator
    {
        yield 'Test without extension' => [
            new MediaLocationStruct('foo', null, 'test', null),
            'media/ac/bd/18/test',
        ];

        yield 'Test with extension' => [
            new MediaLocationStruct('foo', 'jpg', 'test', null),
            'media/ac/bd/18/test.jpg',
        ];

        yield 'Test with extension and cache buster' => [
            new MediaLocationStruct('foo', 'jpg', 'test', new \DateTimeImmutable('2021-01-01')),
            'media/ac/bd/18/1609459200/test.jpg',
        ];

        yield 'Test with thumbnail' => [
            new ThumbnailLocationStruct(
                'foo',
                100,
                100,
                new MediaLocationStruct('foo', 'jpg', 'test', new \DateTimeImmutable('2021-01-01'))
            ),
            'thumbnail/ac/bd/18/1609459200/test_100x100.jpg',
        ];
    }
}
