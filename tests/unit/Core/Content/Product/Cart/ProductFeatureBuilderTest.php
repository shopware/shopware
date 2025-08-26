<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetCollection;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetEntity;
use Shopware\Core\Content\Product\Cart\ProductFeatureBuilder;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Unit\UnitEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductFeatureBuilder::class)]
class ProductFeatureBuilderTest extends TestCase
{
    private ProductFeatureBuilder $productFeatureBuilder;

    /** @var MockObject&EntityRepository<ProductFeatureSetCollection> */
    private MockObject&EntityRepository $customFieldRepository;

    private MockObject&LanguageLocaleCodeProvider $languageLocaleProvider;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->customFieldRepository = $this->createMock(EntityRepository::class);
        $this->languageLocaleProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $this->salesChannelContext = Generator::generateSalesChannelContext();

        $this->productFeatureBuilder = new ProductFeatureBuilder(
            $this->customFieldRepository,
            $this->languageLocaleProvider
        );
    }

    public function testAddFeaturesIntoLineItems(): void
    {
        $productId = 'product-id-123';
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);

        $product = new SalesChannelProductEntity();
        $product->setName('foo');
        $product->setId($productId);

        $group = new PropertyGroupEntity();
        $group->setTranslated(['name' => 'color']);
        $group->setId('group-1');

        $properties = new PropertyGroupOptionCollection();
        $property = new PropertyGroupOptionEntity();
        $property->setName('red');
        $property->setGroupId($group->getId());
        $property->setTranslated(['name' => 'red']);
        $property->setId('option-1');
        $property->setGroup($group);

        $properties->add($property);

        $unit = new UnitEntity();
        $unit->setTranslated(['name' => 'piece']);

        $product->setProperties($properties);
        $product->setUnit($unit);

        $referencePrice = new ReferencePrice(0.5, 1, 1, 'piece');

        $price = new CalculatedPrice(
            unitPrice: 1.0,
            totalPrice: 1.0,
            calculatedTaxes: new CalculatedTaxCollection(),
            taxRules: new TaxRuleCollection(),
            referencePrice: $referencePrice,
        );

        $lineItem->setPrice($price);

        $featureSet = new ProductFeatureSetEntity();
        $featureSet->setFeatures([
            ['name' => 'Color', 'id' => 'group-1', 'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_PROPERTY, 'position' => 1],
            ['name' => 'name', 'id' => 'feature-2', 'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_ATTRIBUTE, 'position' => 2],
            ['name' => 'Feature 4', 'id' => 'feature-4', 'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE, 'position' => 4],
        ]);

        $product->setFeatureSet($featureSet);
        $data = new CartDataCollection();

        $data->set('product-' . $productId, $product);

        $this->productFeatureBuilder->add([$lineItem], $data, $this->salesChannelContext);

        static::assertNotEmpty($lineItem->getPayload()['features']);
        static::assertSame([
            [
                'label' => 'color',
                'value' => [
                    'option-1' => [
                        'id' => 'option-1',
                        'name' => 'red',
                        'mediaId' => null,
                        'colorHexCode' => null,
                    ],
                ],
                'type' => 'property',
            ],
            [
                'label' => 'name',
                'value' => 'foo',
                'type' => 'product',
            ],
            [
                'label' => 'referencePrice',
                'value' => [
                    'price' => 0.5,
                    'purchaseUnit' => 1.0,
                    'referenceUnit' => 1.0,
                    'unitName' => 'piece',
                ],
                'type' => 'referencePrice',
            ],
        ], $lineItem->getPayload()['features']);
    }
}
