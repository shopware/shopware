<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ManufacturerLogoStruct;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Cms\ManufacturerLogoCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ManufacturerLogoTypeCmsResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ManufacturerLogoCmsElementResolver
     */
    private $manufacturerLogoCmsElementResolver;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10078', $this);

        $this->manufacturerLogoCmsElementResolver = $this->getContainer()->get(ManufacturerLogoCmsElementResolver::class);
    }

    public function testType(): void
    {
        static::assertSame('manufacturer-logo', $this->manufacturerLogoCmsElementResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');

        $collection = $this->manufacturerLogoCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutContext(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');

        $this->manufacturerLogoCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ManufacturerLogoStruct|null $manufacturerLogoStruct */
        $manufacturerLogoStruct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $manufacturerLogoStruct);
        static::assertNull($manufacturerLogoStruct->getManufacturer());
    }

    public function testEnrichEntityResolverContext(): void
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer_01');
        $product = new SalesChannelProductEntity();
        $product->setId('product_01');
        $product->setManufacturer($manufacturer);
        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->getContainer()->get(SalesChannelProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');

        $this->manufacturerLogoCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ManufacturerLogoStruct|null $manufacturerLogoStruct */
        $manufacturerLogoStruct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $manufacturerLogoStruct);
        static::assertNotEmpty($manufacturerLogoStruct->getManufacturer());
        static::assertEquals('manufacturer_01', $manufacturerLogoStruct->getManufacturer()->getId());
    }
}
