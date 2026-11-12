<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProvider;
use Shopware\Core\Content\Media\Cms\ImageCmsElementResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsElementMediaIdsProvider::class)]
class CmsElementMediaIdsProviderTest extends TestCase
{
    private const MEDIA_ID = '0190cd1b2e6a73f0b6b4ae5c3a7d9c01';

    private const OTHER_MEDIA_ID = '0190cd1b2e6a73f0b6b4ae5c3a7d9c02';

    public function testSupportsTheTypeOfItsElement(): void
    {
        static::assertTrue($this->createProvider('media')->supports('image'));
        static::assertFalse($this->createProvider('media')->supports('image-slider'));
    }

    /**
     * @param array<string, mixed> $values static values of the config fields
     * @param list<string> $expected
     */
    #[DataProvider('pathProvider')]
    public function testReadsTheMediaByThePath(string $path, array $values, array $expected): void
    {
        $config = array_map(static fn (mixed $value) => ['source' => 'static', 'value' => $value], $values);

        static::assertSame($expected, $this->createProvider($path)->getMediaIds($config));
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

    public function testReadsTheMediaOfEveryPath(): void
    {
        $config = [
            'media' => ['source' => 'static', 'value' => self::MEDIA_ID],
            'previewMedia' => ['source' => 'static', 'value' => self::OTHER_MEDIA_ID],
        ];

        static::assertSame([self::MEDIA_ID, self::OTHER_MEDIA_ID], $this->createProvider('media', 'previewMedia')->getMediaIds($config));
    }

    public function testIgnoresMappedValues(): void
    {
        static::assertSame([], $this->createProvider('media')->getMediaIds(['media' => ['source' => 'mapped', 'value' => 'category.media']]));
    }

    public function testIgnoresOtherFields(): void
    {
        static::assertSame([], $this->createProvider('media')->getMediaIds(['previewMedia' => ['source' => 'static', 'value' => self::MEDIA_ID]]));
    }

    public function testIgnoresMalformedFields(): void
    {
        $config = [
            'media' => ['source' => 'static', 'value' => self::MEDIA_ID],
            'previewMedia' => ['value' => self::OTHER_MEDIA_ID],
        ];

        static::assertSame([self::MEDIA_ID], $this->createProvider('media', 'previewMedia')->getMediaIds($config));
    }

    private function createProvider(string ...$paths): CmsElementMediaIdsProvider
    {
        return new CmsElementMediaIdsProvider(new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class)), array_values($paths));
    }
}
