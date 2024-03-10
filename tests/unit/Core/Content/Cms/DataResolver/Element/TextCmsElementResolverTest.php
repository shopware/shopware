<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(TextCmsElementResolver::class)]
class TextCmsElementResolverTest extends TestCase
{
    private TextCmsElementResolver $textResolver;

    protected function setUp(): void
    {
        $htmlSanitizer = new HtmlSanitizer(null, false, ['basic' => ['tags' => ['h1']]]);
        $this->textResolver = new TextCmsElementResolver($htmlSanitizer);
    }

    public function testType(): void
    {
        static::assertSame('text', $this->textResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = $this->createResolverContext();

        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->textResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = $this->createResolverContext();
        $result = new ElementDataCollection();

        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertNull($textStruct->getContent());
    }

    public function testWithStaticContent(): void
    {
        $resolverContext = $this->createResolverContext();
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, 'lorem ipsum dolor'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('lorem ipsum dolor', $textStruct->getContent());
    }

    public function testWithContaminatedStaticContent(): void
    {
        $resolverContext = $this->createResolverContext();
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, 'lorem<script>console.log("ipsum dolor")</script>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('lorem', $textStruct->getContent());
    }

    public function testWithMappedContent(): void
    {
        $product = new ProductEntity();
        $product->setDescription('foobar loo');

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame($product->getDescription(), $textStruct->getContent());
    }

    public function testWithMappedContentAndTranslationFallback(): void
    {
        $product = new ProductEntity();
        $product->setTranslated(['description' => 'fallback foo']);

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('fallback foo', $textStruct->getContent());
    }

    public function testWithMappedContentAndTranslation(): void
    {
        $product = new ProductEntity();
        $product->setDescription('foobar loo');
        $product->setTranslated(['description' => 'fallback foo']);

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame($product->getDescription(), $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedCustomFieldVariable(): void
    {
        $product = $this->createProductEntity();
        $product->setCustomFields(['testField' => 'testing123']);

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ product.customFields.testField }}</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title testing123</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedVariable(): void
    {
        $product = $this->createProductEntity();

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ product.name }}</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title ' . $product->getName() . '</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedVariableNotFound(): void
    {
        $product = $this->createProductEntity();

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ product.unknownProperty }}</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title {{ product.unknownProperty }}</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndNullValue(): void
    {
        $product = $this->createProductEntity();

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, null));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('', $textStruct->getContent());
    }

    public function testWithStaticContentAndEmptyValue(): void
    {
        $product = $this->createProductEntity();

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, ''));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('', $textStruct->getContent());
    }

    public function testWithStaticContentAndDateTimeValue(): void
    {
        $releaseDate = new \DateTime('2023-06-28T14:27:29');
        $product = $this->createProductEntity();
        $product->setReleaseDate($releaseDate);
        $request = new Request();

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), $request, new ProductDefinition(), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '{{ product.releaseDate }}'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        $content = $textStruct->getContent();
        static::assertIsString($content);

        $formatter = new \IntlDateFormatter($request->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM);
        $actualReleaseDate = new \DateTime();
        $actualReleaseDate->setTimestamp((int) $formatter->parse($content));

        static::assertEquals($releaseDate, $actualReleaseDate);
    }

    private function createSlot(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);

        return $slot;
    }

    private function createResolverContextWithProduct(ProductEntity $product): EntityResolverContext
    {
        return new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), new ProductDefinition(), $product);
    }

    private function createProductEntity(): ProductEntity
    {
        $product = new ProductEntity();
        $product->setName('TextProduct');

        return $product;
    }

    private function createResolverContext(): ResolverContext
    {
        return new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
    }
}
