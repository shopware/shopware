<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\Type\ImageSliderTypeDataResolver;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ImageSliderTypeDataResolver::class)]
class ImageSliderTypeDataResolverTest extends TestCase
{
    public function testCollectCreatesProductMediaCriteriaIfMappedMediaIsNotResolved(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'),
        ]));

        $context = $this->createEntityResolverContext($this->createProductMediaCollectionWithoutMediaAssociation());

        $criteriaCollection = $resolver->collect($slot, $context);

        static::assertNotNull($criteriaCollection);
        static::assertArrayHasKey(ProductMediaDefinition::class, $criteriaCollection->all());
        static::assertArrayHasKey('product_media_slot-id', $criteriaCollection->all()[ProductMediaDefinition::class]);
    }

    public function testCollectSkipsProductMediaCriteriaIfMappedMediaIsResolved(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'),
        ]));

        $context = $this->createEntityResolverContext($this->createProductMediaCollectionWithMediaAssociation());

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectWithStaticConfigStillCreatesMediaCriteria(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $context = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_STATIC, [['mediaId' => 'media-1']]),
        ]));

        $criteriaCollection = $resolver->collect($slot, $context);

        static::assertNotNull($criteriaCollection);
        static::assertArrayHasKey(MediaDefinition::class, $criteriaCollection->all());
        static::assertArrayHasKey('media_slot-id', $criteriaCollection->all()[MediaDefinition::class]);
    }

    public function testEnrichUsesMappedProductMediaResult(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $context = $this->createEntityResolverContext(new ProductMediaCollection());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'),
        ]));

        $result = new ElementDataCollection();
        $result->add('product_media_slot-id', new EntitySearchResult(
            'product_media',
            2,
            $this->createProductMediaCollectionWithMediaAssociation(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $resolver->enrich($slot, $context, $result);

        /** @var ImageSliderStruct $data */
        $data = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $data);
        $sliderItems = $data->getSliderItems();
        static::assertIsArray($sliderItems);
        static::assertCount(2, $sliderItems);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array{string|null, string|null, bool|null}}>
     */
    public static function staticItemUrlProvider(): iterable
    {
        $withoutLink = [null, null, null];
        $withLink = static fn (string $url): array => [$url, 'label', true];

        yield 'url key missing' => [[], $withoutLink];
        yield 'url null' => [['url' => null], $withoutLink];
        yield 'url empty string' => [['url' => ''], $withoutLink];
        yield 'url string zero' => [['url' => '0'], $withLink('0')];
        yield 'url absolute' => [['url' => 'https://example.com'], $withLink('https://example.com')];
        yield 'url relative' => [['url' => '/detail/1'], $withLink('/detail/1')];
        yield 'url whitespace only' => [['url' => ' '], $withLink(' ')];
        yield 'url integer zero' => [['url' => 0], $withoutLink];
        yield 'url float zero' => [['url' => 0.0], $withoutLink];
        yield 'url false' => [['url' => false], $withoutLink];
        yield 'url empty array' => [['url' => []], $withoutLink];
        yield 'url non-zero integer' => [['url' => 1], $withoutLink];
        yield 'url non-zero float' => [['url' => 1.5], $withoutLink];
        yield 'url true' => [['url' => true], $withoutLink];
        yield 'url non-empty array' => [['url' => ['a']], $withoutLink];
    }

    /**
     * @param array<string, mixed> $itemOverrides
     * @param array{string|null, string|null, bool|null} $expected
     */
    #[DataProvider('staticItemUrlProvider')]
    public function testEnrichStaticItemLinkDependsOnUrl(array $itemOverrides, array $expected): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_STATIC, [
                array_merge(['mediaId' => 'media-1', 'ariaLabel' => 'label', 'newTab' => true], $itemOverrides),
            ]),
        ]));

        $result = new ElementDataCollection();
        $result->add('media_slot-id', new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$this->createMediaEntity('media-1')]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & ~(\E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR)) === 0) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $resolver->enrich($slot, new ResolverContext(static::createStub(SalesChannelContext::class), new Request()), $result);
        } finally {
            restore_error_handler();
        }

        $data = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $data);
        $items = $data->getSliderItems();
        static::assertIsArray($items);
        static::assertCount(1, $items);
        static::assertSame($expected, [$items[0]->getUrl(), $items[0]->getAriaLabel(), $items[0]->getNewTab()]);
    }

    private function createEntityResolverContext(ProductMediaCollection $productMedia): EntityResolverContext
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product-1');
        $product->setMedia($productMedia);

        return new EntityResolverContext(
            static::createStub(SalesChannelContext::class),
            new Request(),
            static::createStub(EntityDefinition::class),
            $product
        );
    }

    private function createProductMediaCollectionWithMediaAssociation(): ProductMediaCollection
    {
        $item = new ProductMediaEntity();
        $item->setId('pm-1');
        $item->setMediaId('media-1');
        $item->setMedia($this->createMediaEntity('media-1'));

        $item2 = new ProductMediaEntity();
        $item2->setId('pm-2');
        $item2->setMediaId('media-2');
        $item2->setMedia($this->createMediaEntity('media-2'));

        return new ProductMediaCollection([$item, $item2]);
    }

    private function createProductMediaCollectionWithoutMediaAssociation(): ProductMediaCollection
    {
        $item = new ProductMediaEntity();
        $item->setId('pm-1');
        $item->setMediaId('media-1');

        return new ProductMediaCollection([$item]);
    }

    private function createMediaEntity(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);

        return $media;
    }
}
