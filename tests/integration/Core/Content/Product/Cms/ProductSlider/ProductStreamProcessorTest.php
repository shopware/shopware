<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Cms\ProductSlider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class ProductStreamProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->createStreamAndProducts();
    }

    public function testStreamSliderHidesOutOfStockCloseoutProductsWhenSettingEnabled(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $products = $this->resolveStreamSlider();

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertTrue($products->has($this->ids->get('available-product')));
        static::assertFalse(
            $products->has($this->ids->get('closeout-oos-product')),
            'OOS closeout product must not appear in stream slider when hideCloseoutProductsWhenOutOfStock is enabled'
        );
    }

    public function testStreamSliderKeepsOutOfStockCloseoutProductsWhenSettingDisabled(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', false);

        $products = $this->resolveStreamSlider();

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertTrue($products->has($this->ids->get('available-product')));
        static::assertTrue($products->has($this->ids->get('closeout-oos-product')));
    }

    public function testHiddenCloseoutProductsDoNotConsumeSliderLimit(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        // stock ASC puts the OOS closeout product first; with a limit of 1 it would
        // consume the only slot if filtering happened after the stream search.
        $products = $this->resolveStreamSlider(1, 'stock:ASC');

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertTrue(
            $products->has($this->ids->get('available-product')),
            'Hidden closeout products must not consume slider limit slots'
        );
    }

    private function resolveStreamSlider(?int $limit = null, ?string $sorting = null): ?ProductCollection
    {
        $resolverContext = new ResolverContext($this->context, new Request());

        $config = new FieldConfig('products', 'product_stream', $this->ids->get('stream'));
        $configs = new FieldConfigCollection();
        $configs->add($config);

        if ($limit !== null) {
            $configs->add(new FieldConfig('productStreamLimit', FieldConfig::SOURCE_STATIC, $limit));
        }

        if ($sorting !== null) {
            $configs->add(new FieldConfig('productStreamSorting', FieldConfig::SOURCE_STATIC, $sorting));
        }

        $slot = new CmsSlotEntity();
        $slot->setId(Uuid::randomHex());
        $slot->setType('product-slider');
        $slot->setSlot('productSlider');
        $slot->setFieldConfig($configs);
        $slot->setBlockId(Uuid::randomHex());

        $slots = new CmsSlotCollection();
        $slots->add($slot);

        $resolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        $result = $resolver->resolve($slots, $resolverContext);

        $data = $result->first()?->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $data);

        return $data->getProducts();
    }

    private function createStreamAndProducts(): void
    {
        $context = Context::createDefaultContext();

        static::getContainer()->get('product_stream.repository')->create([
            [
                'id' => $this->ids->get('stream'),
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ],
                ],
                'name' => 'testStream',
            ],
        ], $context);

        $products = [
            (new ProductBuilder($this->ids, 'available-product'))
                ->price(100)
                ->visibility()
                ->stock(10)
                ->closeout(false)
                ->build(),
            (new ProductBuilder($this->ids, 'closeout-oos-product'))
                ->price(100)
                ->visibility()
                ->stock(0)
                ->closeout(true)
                ->build(),
        ];

        static::getContainer()->get('product.repository')->create($products, $context);

        // Products created via ProductBuilder get their own tax; the SalesChannelContext
        // needs to know those taxes so price calculation during slider resolution does
        // not fail with "Could not find tax with id ...".
        foreach ($products as $product) {
            if (isset($product['tax'])) {
                $this->addTaxDataToSalesChannel($this->context, $product['tax']);
            }
        }
    }
}
