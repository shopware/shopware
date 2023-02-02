<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SlotDataResolver\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class TextTypeDataResolverTest extends TestCase
{
    use KernelTestBehaviour;

    private TextCmsElementResolver $textResolver;

    protected function setUp(): void
    {
        $this->textResolver = new TextCmsElementResolver($this->getContainer()->get(HtmlSanitizer::class));
    }

    public function testType(): void
    {
        static::assertSame('text', $this->textResolver->getType());
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
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertNull($textStruct->getContent());
    }

    public function testWithStaticContent(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, 'lorem ipsum dolor'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('lorem ipsum dolor', $textStruct->getContent());
    }

    public function testWithUnsanitizedStaticContent(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, 'lorem<script>console.log("ipsum dolor")</script>'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('lorem', $textStruct->getContent());
    }

    public function testWithMappedContent(): void
    {
        $product = new ProductEntity();
        $product->setDescription('foobar loo');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame($product->getDescription(), $textStruct->getContent());
    }

    public function testWithMappedContentAndTranslationFallback(): void
    {
        $product = new ProductEntity();
        $product->setTranslated(['description' => 'fallback foo']);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('fallback foo', $textStruct->getContent());
    }

    public function testWithMappedContentAndTranslation(): void
    {
        $product = new ProductEntity();
        $product->setDescription('foobar loo');
        $product->setTranslated(['description' => 'fallback foo']);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame($product->getDescription(), $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedCustomFieldVariable(): void
    {
        $product = new ProductEntity();
        $product->setName('TextProduct');
        $product->setCustomFields(['testfield' => 'testing123']);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ product.customFields.testfield }}</h1>'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title testing123</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedVariable(): void
    {
        $product = new ProductEntity();
        $product->setName('TextProduct');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ product.name }}</h1>'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title ' . $product->getName() . '</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedVariableNotFound(): void
    {
        $product = new ProductEntity();
        $product->setName('TextProduct');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ product.unknownProperty }}</h1>'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title {{ product.unknownProperty }}</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndNullValue(): void
    {
        $product = new ProductEntity();
        $product->setName('TextProduct');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('', $textStruct->getContent());
    }

    public function testWithStaticContentAndEmptyValue(): void
    {
        $product = new ProductEntity();
        $product->setName('TextProduct');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('', $textStruct->getContent());
    }

    public function testWithStaticContentAndDateTimeValue(): void
    {
        $product = new ProductEntity();
        $product->setName('TextProduct');
        $product->setReleaseDate(new \DateTime());

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '{{ product.releaseDate }}'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        $content = $textStruct->getContent();
        static::assertIsString($content);
        static::assertNotFalse(strtotime($content));
    }
}
