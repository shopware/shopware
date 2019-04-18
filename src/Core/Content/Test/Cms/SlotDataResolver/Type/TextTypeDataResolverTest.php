<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SlotDataResolver\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Cms\SlotDataResolver\Type\TextTypeDataResolver;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class TextTypeDataResolverTest extends TestCase
{
    /**
     * @var TextTypeDataResolver
     */
    private $textResolver;

    protected function setUp(): void
    {
        $this->textResolver = new TextTypeDataResolver();
    }

    public function testType(): void
    {
        static::assertEquals('text', $this->textResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->textResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new SlotDataResolveResult();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->textResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(TextStruct::class, $slot->getData());
        static::assertNull($slot->getData()->getContent());
    }

    public function testWithStaticContent(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new SlotDataResolveResult();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, 'lorem ipsum dolor'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(TextStruct::class, $slot->getData());
        static::assertEquals('lorem ipsum dolor', $slot->getData()->getContent());
    }

    public function testWithMappedContent(): void
    {
        $product = new ProductEntity();
        $product->setDescription('foobar loo');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), ProductDefinition::class, $product);
        $result = new SlotDataResolveResult();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(TextStruct::class, $slot->getData());
        static::assertEquals($product->getDescription(), $slot->getData()->getContent());
    }
}
