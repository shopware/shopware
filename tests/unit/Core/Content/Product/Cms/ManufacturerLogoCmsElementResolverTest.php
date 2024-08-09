<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ManufacturerLogoStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Cms\ManufacturerLogoCmsElementResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(ManufacturerLogoCmsElementResolver::class)]
class ManufacturerLogoCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new ManufacturerLogoCmsElementResolver();
        static::assertSame('manufacturer-logo', $resolver->getType());
    }

    public function testCollectCreatesMediaCriteria(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $elements = $collection->all();
        static::assertCount(1, $elements);
        static::assertArrayHasKey(MediaDefinition::class, $elements);

        $definitionData = array_shift($elements);
        static::assertCount(1, $definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = array_shift($definitionData);
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertEquals(['media-1'], $criteria->getIds());
    }

    public function testCollectReturnsNullWithEmptyConfig(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, null),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $resolver = new ManufacturerLogoCmsElementResolver();
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testEnrichStaticSlotWithManufacturerLogo(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
            new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://localhost'),
            new FieldConfig('newTab', FieldConfig::SOURCE_STATIC, true),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());

        $media = new MediaEntity();
        $media->setId('media-1');

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('get')->with('media-1')->willReturn($media);

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $result);

        $resolver = new ManufacturerLogoCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $data);
        static::assertSame('media-1', $data->getMediaId());
        static::assertSame('http://localhost', $data->getUrl());
        static::assertTrue($data->getNewTab());

        $media = $data->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('media-1', $media->getId());
    }
}
