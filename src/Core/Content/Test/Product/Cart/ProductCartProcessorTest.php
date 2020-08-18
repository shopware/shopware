<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

class ProductCartProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public const TEST_LANGUAGE_LOCALE_CODE = 'sw-AG';
    public const TEST_LANGUAGE_ID = 'cc72c24b82684d72a4ce91054da264bf';
    public const TEST_LOCALE_ID = 'cf735c44dc7b4428bb3870fe4ffea2df';
    public const CUSTOM_FIELD_ID = '24c8b3e8cacc4bf2a743b8c5a7522a33';

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var CartService
     */
    private $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->cartService = $this->getContainer()->get(CartService::class);
    }

    public function testDeliveryInformation(): void
    {
        $this->createProduct();

        $cart = $this->getProductCart();
        $lineItem = $cart->get($this->ids->get('product'));

        static::assertInstanceOf(DeliveryInformation::class, $lineItem->getDeliveryInformation());

        $info = $lineItem->getDeliveryInformation();
        static::assertEquals(100, $info->getWeight());
        static::assertEquals(101, $info->getHeight());
        static::assertEquals(102, $info->getWidth());
        static::assertEquals(103, $info->getLength());
    }

    public function testOverwriteLabelNoPermission(): void
    {
        $this->createProduct();
        $service = $this->getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $context = $this->getContainer()->get(SalesChannelContextService::class)
            ->get(Defaults::SALES_CHANNEL, $token);

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $product->setLabel('My special product');

        /** @var Cart $cart */
        $cart = $service->getCart($token, $context);
        $service->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());
        static::assertSame('test', $actualProduct->getLabel());
    }

    public function testOverwriteLabelWithPermission(): void
    {
        $this->createProduct();
        $service = $this->getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $options = [
            SalesChannelContextService::PERMISSIONS => [ProductCartProcessor::ALLOW_PRODUCT_LABEL_OVERWRITES => true],
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, Defaults::SALES_CHANNEL, $options);

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $product->setLabel('My special product');

        /** @var Cart $cart */
        $cart = $service->getCart($token, $context);
        $service->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());
        static::assertSame($product->getLabel(), $actualProduct->getLabel());
    }

    public function testOverwriteLabelWithPermissionNoLabel(): void
    {
        $this->createProduct();
        $service = $this->getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $options = [
            SalesChannelContextService::PERMISSIONS => [ProductCartProcessor::ALLOW_PRODUCT_LABEL_OVERWRITES => true],
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, Defaults::SALES_CHANNEL, $options);

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $product->setLabel(null);

        /** @var Cart $cart */
        $cart = $service->getCart($token, $context);
        $service->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());
        static::assertSame('test', $actualProduct->getLabel());
    }

    public function testPayloadContainsFeatures(): void
    {
        $this->createProduct();

        $cart = $this->getProductCart();
        $lineItem = $cart->get($this->ids->get('product'));

        static::assertArrayHasKey('features', $lineItem->getPayload());
    }

    /**
     * @dataProvider productFeatureProdiver
     */
    public function testProductFeaturesContainCorrectInformation(array $testedFeature, array $productData, array $expectedFeature): void
    {
        $this->createLanguage(self::TEST_LANGUAGE_ID);

        if ($testedFeature['type'] === ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD) {
            $this->createCustomField([]);
        }

        $this->createProduct(array_merge([
            'featureSet' => $this->createFeatureSet([$testedFeature]),
        ], $productData));

        $cart = $this->getProductCart();
        $lineItem = $cart->get($this->ids->get('product'));

        $features = $lineItem->getPayload()['features'];
        $feature = array_pop($features);

        static::assertArrayHasKey('label', $feature);
        static::assertArrayHasKey('value', $feature);
        static::assertArrayHasKey('type', $feature);

        if ($testedFeature['type'] === ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE) {
            unset($expectedFeature['value']['price'], $feature['value']['price']);
        }

        static::assertEquals($expectedFeature, $feature);
    }

    public function productFeatureProdiver(): array
    {
        return [
            [
                [
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_ATTRIBUTE,
                    'id' => null,
                    'name' => 'description',
                    'position' => 1,
                ],
                [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => 'Default',
                            'description' => 'Default',
                        ],
                        self::TEST_LANGUAGE_ID => [
                            'description' => 'Lorem ipsum dolor sit amet.',
                        ],
                    ],
                ],
                [
                    'label' => 'description',
                    'value' => 'Lorem ipsum dolor sit amet.',
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_ATTRIBUTE,
                ],
            ],
            [
                [
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_ATTRIBUTE,
                    'id' => null,
                    'name' => 'manufacturerNumber',
                    'position' => 1,
                ],
                [
                    'manufacturerNumber' => '22ee3d8063da',
                ],
                [
                    'label' => 'manufacturerNumber',
                    'value' => '22ee3d8063da',
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_ATTRIBUTE,
                ],
            ],
            [
                [
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_PROPERTY,
                    'id' => '7c8e7851ff88447ba254d3c2a7c45101',
                    'name' => null,
                    'position' => 2,
                ],
                [
                    'properties' => [
                        [
                            'id' => 'bf821e9e206848579049bc1694c5c3e7',
                        ],
                        [
                            'id' => '0cfabe6eab0440b0974b7b7164556612',
                        ],
                    ],
                    'options' => [
                        [
                            'id' => 'bf821e9e206848579049bc1694c5c3e7',
                            'position' => 99,
                            'colorHexCode' => '#189eff',
                            'group' => [
                                'id' => '7c8e7851ff88447ba254d3c2a7c45101',
                                'position' => 1,
                                'translations' => [
                                    Defaults::LANGUAGE_SYSTEM => [
                                        'name' => 'Default',
                                        'description' => 'Default',
                                        'displayType' => 'Default',
                                        'sortingType' => 'Default',
                                    ],
                                    self::TEST_LANGUAGE_ID => [
                                        'name' => 'swag_color',
                                        'description' => 'Lorem ipsum',
                                        'displayType' => 'color',
                                        'sortingType' => 'alphanumeric',
                                    ],
                                ],
                            ],
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => [
                                    'name' => 'Default',
                                ],
                                self::TEST_LANGUAGE_ID => [
                                    'name' => 'Blue',
                                ],
                            ],
                        ],
                        [
                            'id' => '0cfabe6eab0440b0974b7b7164556612',
                            'position' => 98,
                            'colorHexCode' => '#ff0000',
                            'groupId' => '7c8e7851ff88447ba254d3c2a7c45101',
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => [
                                    'name' => 'Default',
                                ],
                                self::TEST_LANGUAGE_ID => [
                                    'name' => 'Red',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'label' => 'swag_color',
                    'value' => [
                        '0cfabe6eab0440b0974b7b7164556612' => [
                            'id' => '0cfabe6eab0440b0974b7b7164556612',
                            'name' => 'Red',
                            'mediaId' => null,
                            'colorHexCode' => '#ff0000',
                        ],
                        'bf821e9e206848579049bc1694c5c3e7' => [
                            'id' => 'bf821e9e206848579049bc1694c5c3e7',
                            'name' => 'Blue',
                            'mediaId' => null,
                            'colorHexCode' => '#189eff',
                        ],
                    ],
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_PROPERTY,
                ],
            ],
            [
                [
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD,
                    'id' => null,
                    'name' => 'lorem_ipsum',
                    'position' => 3,
                ],
                [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => 'Default',
                            'customFields' => [
                                'lorem_ipsum' => 'Default',
                            ],
                        ],
                        self::TEST_LANGUAGE_ID => [
                            'customFields' => [
                                'lorem_ipsum' => 'Dolor sit amet.',
                            ],
                        ],
                    ],
                ],
                [
                    'label' => 'lorem_ipsum',
                    'value' => [
                        'id' => self::CUSTOM_FIELD_ID,
                        'type' => CustomFieldTypes::TEXT,
                        'content' => 'Dolor sit amet.',
                    ],
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD,
                ],
            ],
            [
                [
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE,
                    'id' => null,
                    'name' => null,
                    'position' => 0,
                ],
                [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => 'Default',
                            'packUnit' => 'Default',
                            'packUnitPlural' => 'Default',
                        ],
                        self::TEST_LANGUAGE_ID => [
                            'packUnit' => 'Can',
                            'packUnitPlural' => 'Cans',
                        ],
                    ],
                    'unit' => [
                        'translations' => [
                            Defaults::LANGUAGE_SYSTEM => [
                                'shortCode' => 'Default',
                                'name' => 'Default',
                            ],
                            self::TEST_LANGUAGE_ID => [
                                'shortCode' => 'l',
                                'name' => 'litres',
                            ],
                        ],
                    ],
                    'purchaseUnit' => 2,
                    'referenceUnit' => 0.33,
                ],
                [
                    'label' => ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE,
                    'value' => [
                        'purchaseUnit' => 2.0,
                        'referenceUnit' => 0.33,
                        'unitName' => 'litres',
                    ],
                    'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE,
                ],
            ],
        ];
    }

    private function getProductCart(): Cart
    {
        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $token = $this->ids->create('token');
        $context = $this->getContainer()->get(SalesChannelContextService::class)
            ->get(Defaults::SALES_CHANNEL, $token);

        $cart = $this->cartService->getCart($token, $context);
        $this->cartService->add($cart, $product, $context);

        return $cart;
    }

    private function createProduct(?array $additionalData = []): void
    {
        $data = array_merge([
            'id' => $this->ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'active' => true,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'weight' => 100,
            'height' => 101,
            'width' => 102,
            'length' => 103,
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test',
                ],
            ],
        ], $additionalData);

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function createCustomField(?array $additionalData = []): void
    {
        $data = array_merge([
            'id' => self::CUSTOM_FIELD_ID,
            'name' => 'lorem_ipsum',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'componentName' => 'sw-field',
                'customFieldPosition' => 1,
                'customFieldType' => CustomFieldTypes::TEXT,
                'type' => CustomFieldTypes::TEXT,
                'label' => [
                    'en-GB' => 'lorem_ipsum',
                    'de-DE' => 'lorem_ipsum',
                ],
            ],
        ], $additionalData);

        $this->getContainer()->get('custom_field.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function createFeatureSet(?array $features = []): array
    {
        return [
            'id' => $this->ids->create('product-feature-set'),
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'Test feature set',
                    'description' => 'Lorem ipsum dolor sit amet',
                ],
            ],
            'features' => $features,
        ];
    }

    private function createLanguage(string $id, ?string $parentId = Defaults::LANGUAGE_SYSTEM): void
    {
        /* @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');

        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'parentId' => $parentId,
                    'translationCode' => [
                        'id' => self::TEST_LOCALE_ID,
                        'code' => self::TEST_LANGUAGE_LOCALE_CODE,
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'salesChannels' => [
                        ['id' => Defaults::SALES_CHANNEL],
                    ],
                    'salesChannelDefaultAssignments' => [
                        ['id' => Defaults::SALES_CHANNEL],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
