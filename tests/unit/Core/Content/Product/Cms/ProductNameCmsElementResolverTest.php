<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Shopware\Core\Content\Product\Cms\ProductNameCmsElementResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(ProductNameCmsElementResolver::class)]
class ProductNameCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new ProductNameCmsElementResolver();
        static::assertSame('product-name', $resolver->getType());
    }

    public function testEnrichSetsEmptyTextStructWithoutConfig(): void
    {
        $slot = new CmsSlotEntity();
        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver = new ProductNameCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $data);
        static::assertNull($data->getContent());
    }

    public function testEnrichStaticContent(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('content', FieldConfig::SOURCE_STATIC, 'my-value'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::createSalesChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver = new ProductNameCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $data);
        static::assertSame('my-value', $data->getContent());
    }
}
