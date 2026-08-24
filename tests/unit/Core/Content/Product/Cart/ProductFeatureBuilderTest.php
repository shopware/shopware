<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
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
    private const LANGUAGE_ID = 'language-id-123';

    private const CHILD_LANGUAGE_ID = 'child-language-id-123';

    private const LANGUAGE_CHAIN = [self::LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM];

    private const CHILD_LANGUAGE_CHAIN = [self::CHILD_LANGUAGE_ID, self::LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM];

    private ProductFeatureBuilder $productFeatureBuilder;

    /**
     * @var Stub&EntityRepository<ProductFeatureSetCollection>
     */
    private Stub&EntityRepository $customFieldRepository;

    private Stub&LanguageLocaleCodeProvider $languageLocaleProvider;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->customFieldRepository = static::createStub(EntityRepository::class);
        $this->languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
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

    public function testCustomFieldLabelUsesTheLanguageOfTheContext(): void
    {
        $features = $this->buildCustomFieldFeatures([
            'en-GB' => 'Material',
            'de-DE' => 'Werkstoff',
        ]);

        static::assertSame([
            [
                'label' => 'Werkstoff',
                'value' => [
                    'id' => 'custom-field-id',
                    'type' => CustomFieldTypes::TEXT,
                    'content' => 'wood',
                ],
                'type' => 'customField',
            ],
        ], $features);
    }

    /**
     * A child language inherits the label of its parent language before the system language is used.
     *
     * @param array<string, string> $labels
     */
    #[DataProvider('parentLanguageLabelProvider')]
    public function testCustomFieldLabelFallsBackToTheParentLanguage(array $labels): void
    {
        $features = $this->buildCustomFieldFeatures($labels, self::CHILD_LANGUAGE_CHAIN);

        static::assertSame('Werkstoff', $features[0]['label']);
    }

    /**
     * @param array<string, string> $labels
     */
    #[DataProvider('missingLabelProvider')]
    public function testCustomFieldLabelFallsBackToTheSystemLanguage(array $labels): void
    {
        $features = $this->buildCustomFieldFeatures($labels);

        static::assertSame('Material', $features[0]['label']);
    }

    /**
     * @param array<string, string> $labels
     */
    #[DataProvider('skippedLabelProvider')]
    public function testCustomFieldIsSkippedIfNoLanguageOfTheChainHasALabel(array $labels): void
    {
        static::assertSame([], $this->buildCustomFieldFeatures($labels));
    }

    public function testCustomFieldIsSkippedIfTheSystemLanguageLabelIsEmpty(): void
    {
        static::assertSame([], $this->buildCustomFieldFeatures(['en-GB' => ''], [Defaults::LANGUAGE_SYSTEM]));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function parentLanguageLabelProvider(): iterable
    {
        yield 'no label for the child language' => [['en-GB' => 'Material', 'de-DE' => 'Werkstoff']];
        yield 'empty label for the child language' => [['en-GB' => 'Material', 'de-DE' => 'Werkstoff', 'de-AT' => '']];
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function missingLabelProvider(): iterable
    {
        yield 'no label for the context language' => [['en-GB' => 'Material']];
        yield 'empty label for the context language' => [['en-GB' => 'Material', 'de-DE' => '']];
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function skippedLabelProvider(): iterable
    {
        yield 'no label for any language of the chain' => [['fr-FR' => 'Matériau']];
        yield 'empty system language label' => [['en-GB' => '']];
    }

    /**
     * Builds the features of a line item referencing a product with a single custom field feature.
     * The context defaults to the `de-DE` language chain; the system language is `en-GB` throughout.
     *
     * @param array<string, string> $labels
     * @param non-empty-list<string> $languageIdChain
     *
     * @return array<int, array{label: string, value: mixed, type: string}>
     */
    private function buildCustomFieldFeatures(array $labels, array $languageIdChain = self::LANGUAGE_CHAIN): array
    {
        $this->languageLocaleProvider->method('getLocaleForLanguageId')->willReturnMap([
            [self::CHILD_LANGUAGE_ID, 'de-AT'],
            [self::LANGUAGE_ID, 'de-DE'],
            [Defaults::LANGUAGE_SYSTEM, 'en-GB'],
        ]);

        $productId = 'product-id-123';
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);
        $product->setTranslated(['customFields' => ['custom_material' => 'wood']]);

        $featureSet = new ProductFeatureSetEntity();
        $featureSet->setFeatures([
            ['name' => 'custom_material', 'id' => 'feature-1', 'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD, 'position' => 1],
        ]);
        $product->setFeatureSet($featureSet);

        $customField = new CustomFieldEntity();
        $customField->setId('custom-field-id');
        $customField->setName('custom_material');
        $customField->setType(CustomFieldTypes::TEXT);
        $customField->setConfig(['label' => $labels]);

        $data = new CartDataCollection();
        $data->set('product-' . $productId, $product);
        $data->set('custom-field-custom_material', $customField);

        $context = Generator::generateSalesChannelContext(
            baseContext: new Context(new SystemSource(), languageIdChain: $languageIdChain),
        );

        $this->productFeatureBuilder->add([$lineItem], $data, $context);

        return $lineItem->getPayload()['features'];
    }
}
