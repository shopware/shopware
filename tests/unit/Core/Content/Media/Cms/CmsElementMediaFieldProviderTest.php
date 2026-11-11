<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Media\Cms\CmsElementMediaFieldProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsElementMediaFieldProvider::class)]
class CmsElementMediaFieldProviderTest extends TestCase
{
    private const MEDIA_ID = '0190cd1b2e6a73f0b6b4ae5c3a7d9c01';

    private const OTHER_MEDIA_ID = '0190cd1b2e6a73f0b6b4ae5c3a7d9c02';

    public function testIsRegisteredForItsElementType(): void
    {
        static::assertSame('image', (new CmsElementMediaFieldProvider('image', 'media'))->getType());
    }

    /**
     * @param array<string, mixed> $values static values of the config fields
     * @param list<string> $expected
     */
    #[DataProvider('pathProvider')]
    public function testReadsTheMediaByThePath(string $path, array $values, array $expected): void
    {
        $config = array_map(static fn (mixed $value) => ['source' => 'static', 'value' => $value], $values);

        static::assertSame($expected, (new CmsElementMediaFieldProvider('image', $path))->getMediaIds($this->createSlot($config)));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, list<string>}>
     */
    public static function pathProvider(): iterable
    {
        yield 'a media id in a field, like the image element' => ['media', ['media' => self::MEDIA_ID], [self::MEDIA_ID]];
        yield 'a list of media ids in a field' => ['media', ['media' => [self::MEDIA_ID, self::OTHER_MEDIA_ID]], [self::MEDIA_ID, self::OTHER_MEDIA_ID]];
        yield 'media ids in upper case' => ['media', ['media' => strtoupper(self::MEDIA_ID)], [strtoupper(self::MEDIA_ID)]];
        yield 'a media id in an item' => ['media.id', ['media' => ['id' => self::MEDIA_ID, 'url' => null]], [self::MEDIA_ID]];
        yield 'the media ids of every item of a list, like the image slider' => [
            'sliderItems.*.mediaId',
            ['sliderItems' => [['mediaId' => self::MEDIA_ID, 'url' => null], ['mediaId' => self::OTHER_MEDIA_ID]]],
            [self::MEDIA_ID, self::OTHER_MEDIA_ID],
        ];
        yield 'media ids nested in lists of items' => [
            'slider.*.item.media.*.id',
            ['slider' => [
                ['item' => ['media' => [['id' => self::MEDIA_ID], ['id' => self::OTHER_MEDIA_ID]]]],
                ['item' => ['media' => []]],
                ['item' => null],
            ]],
            [self::MEDIA_ID, self::OTHER_MEDIA_ID],
        ];
        yield 'an entry of a list addressed by its index' => [
            'slider.1.mediaId',
            ['slider' => [['mediaId' => self::MEDIA_ID], ['mediaId' => self::OTHER_MEDIA_ID]]],
            [self::OTHER_MEDIA_ID],
        ];
        yield 'items are not media ids' => ['sliderItems', ['sliderItems' => [['mediaId' => self::MEDIA_ID]]], []];
        yield 'a path which does not exist' => ['slider.*.image', ['slider' => [['mediaId' => self::MEDIA_ID]]], []];
        yield 'no media chosen' => ['media', ['media' => null], []];
        yield 'values which are no ids, like URLs' => ['media.*', ['media' => ['https://example.com/image.png', 'not-an-id', 42]], []];
    }

    public function testIgnoresMappedValues(): void
    {
        $slot = $this->createSlot(['media' => ['source' => 'mapped', 'value' => 'category.media']]);

        static::assertSame([], (new CmsElementMediaFieldProvider('image', 'media'))->getMediaIds($slot));
    }

    public function testIgnoresOtherFields(): void
    {
        $slot = $this->createSlot(['previewMedia' => ['source' => 'static', 'value' => self::MEDIA_ID]]);

        static::assertSame([], (new CmsElementMediaFieldProvider('image', 'media'))->getMediaIds($slot));
    }

    /**
     * @param array<string, array{source: string, value: mixed}> $config
     */
    private function createSlot(array $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setConfig($config);
        $slot->addTranslated('config', $config);

        return $slot;
    }
}
