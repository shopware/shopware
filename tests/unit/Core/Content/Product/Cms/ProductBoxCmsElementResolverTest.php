<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Product\Cms\ProductBoxCmsElementResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductBoxCmsElementResolver::class)]
class ProductBoxCmsElementResolverTest extends TestCase
{
    private ProductBoxCmsElementResolver $boxCmsElementResolver;

    private MockObject&SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        $this->systemConfig = $this->createMock(SystemConfigService::class);

        $this->boxCmsElementResolver = new ProductBoxCmsElementResolver($this->systemConfig);
    }

    public function testGetType(): void
    {
        static::assertSame('product-box', $this->boxCmsElementResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $collection = $this->boxCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithEmptyStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection([
            new FieldConfig('products', FieldConfig::SOURCE_STATIC, []),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->boxCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $productId = Uuid::randomHex();
        $fieldConfig = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->boxCmsElementResolver->collect($slot, $resolverContext);

        static::assertNotNull($collection);
        static::assertCount(1, $collection->all());
        static::assertSame([$productId], $collection->all()[ProductDefinition::class]['product_id']->getIds());
    }

    public function testCollectWithMappedConfigButWithoutEntityResolverContext(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'category.products'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->boxCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithNoProductConfig(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $resolverContext = new ResolverContext($salesChannelContext, new Request());
        $result = new ElementDataCollection();
        $fieldConfig = new FieldConfigCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('');
        $slot->setFieldConfig($fieldConfig);

        $this->boxCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductBoxStruct|null $productBoxStruct */
        $productBoxStruct = $slot->getData();

        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);

        $product = $productBoxStruct->getProduct();
        static::assertNull($product);
    }

    public function testEnrichWithProductConfigIsMapped(): void
    {
        $productId = Uuid::randomHex();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $product = new SalesChannelProductEntity();
        $product->setId($productId);
        $product->setUniqueIdentifier('product1');

        $resolverContext = new EntityResolverContext($salesChannelContext, new Request(), $this->createMock(SalesChannelProductDefinition::class), $product);
        $result = new ElementDataCollection();
        $fieldConfig = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_MAPPED, $productId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('');
        $slot->setFieldConfig($fieldConfig);

        $this->boxCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductBoxStruct|null $productBoxStruct */
        $productBoxStruct = $slot->getData();

        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);

        $product = $productBoxStruct->getProduct();
        static::assertNotNull($product);
        static::assertEquals($productId, $product->getId());
    }

    #[DataProvider('enrichDataProvider')]
    public function testEnrich(bool $closeout, bool $hidden, int $availableStock): void
    {
        if ($hidden) {
            $this->systemConfig->method('getBool')->willReturn(true);
        }

        $salesChannelId = 'f3489c46df62422abdea4aa1bb03511c';
        $productId = Uuid::randomHex();
        $product = new SalesChannelProductEntity();
        $product->setId($productId);
        $product->setStock($availableStock);
        $product->setAvailableStock($availableStock);
        $product->setIsCloseout($closeout);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn($salesChannelId);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $resolverContext = new ResolverContext($salesChannelContext, new Request());
        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('');
        $slot->setFieldConfig($fieldConfig);

        $this->boxCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductBoxStruct|null $productBoxStruct */
        $productBoxStruct = $slot->getData();

        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);

        $product = $productBoxStruct->getProduct();

        if ($closeout && $hidden && $availableStock === 0) {
            static::assertNull($product);
        } else {
            static::assertNotNull($product);
            static::assertSame($productId, $product->getId());
        }
    }

    /**
     * @return array<array<bool|int>> closeout, hidden, availableStock
     */
    public static function enrichDataProvider(): array
    {
        return [
            [false, false, 1],
            [false, true, 1],
            [true, false, 1],
            [true, true, 1],
            [true, true, 0],
        ];
    }
}
