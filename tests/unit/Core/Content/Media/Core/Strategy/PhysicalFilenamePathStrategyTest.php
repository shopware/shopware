<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Strategy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopware\Core\Content\Media\Core\Strategy\PhysicalFilenamePathStrategy;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Core\Strategy\PhysicalFilenamePathStrategy
 */
class PhysicalFilenamePathStrategyTest extends TestCase
{
    /**
     * @dataProvider strategyProvider
     */
    public function testStrategy(MediaLocationStruct|ThumbnailLocationStruct $struct, ?string $expected): void
    {
        $strategy = new PhysicalFilenamePathStrategy();

        $generate = $strategy->generate([$struct]);

        if ($expected === null) {
            static::assertArrayNotHasKey($struct->id, $generate);

            return;
        }

        static::assertArrayHasKey($struct->id, $generate);

        static::assertSame($expected, $generate[$struct->id]);
    }

    public static function strategyProvider(): \Generator
    {
        yield 'Test without extension' => [
            new MediaLocationStruct('foo', null, 'test', null),
            null,
        ];

        yield 'Test with extension' => [
            new MediaLocationStruct('foo', 'jpg', 'test', null),
            'media/09/8f/6b/test.jpg',
        ];

        yield 'Test with extension and cache buster' => [
            new MediaLocationStruct('foo', 'jpg', 'test', new \DateTimeImmutable('2021-01-01')),
            'media/49/6b/53/1609459200/test.jpg',
        ];

        yield 'Test with thumbnail' => [
            new ThumbnailLocationStruct(
                'foo',
                100,
                100,
                new MediaLocationStruct('foo', 'jpg', 'test', new \DateTimeImmutable('2021-01-01'))
            ),
            'thumbnail/49/6b/53/1609459200/test_100x100.jpg',
        ];
    }
}
