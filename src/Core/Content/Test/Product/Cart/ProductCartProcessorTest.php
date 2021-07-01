<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductCartProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public const TEST_LANGUAGE_LOCALE_CODE = 'sw-AG';
    public const TEST_LANGUAGE_ID = 'cc72c24b82684d72a4ce91054da264bf';
    public const TEST_LOCALE_ID = 'cf735c44dc7b4428bb3870fe4ffea2df';
    public const CUSTOM_FIELD_ID = '24c8b3e8cacc4bf2a743b8c5a7522a33';
    public const PURCHASE_STEP_QUANTITY_ERROR_KEY = 'purchase-steps-quantity';

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var QuantityPriceCalculator
     */
    private $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->calculator = $this->getContainer()->get(QuantityPriceCalculator::class);
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

    public function testNotCompletedLogic(): void
    {
        $context = $this->getContext();

        $this->createProduct();
        $cart = $this->getProductCart();

        $lineItem = $cart->get($this->ids->get('product'));
        static::assertSame('test', $lineItem->getLabel());

        $update = ['id' => $this->ids->get('product'), 'name' => 'update'];
        $this->getContainer()->get('product.repository')->upsert([$update], $context->getContext());

        $cart = $this->cartService->getCart($context->getToken(), $this->getContext(), CartService::SALES_CHANNEL, false);

        $lineItem = $cart->get($this->ids->get('product'));
        static::assertSame('update', $lineItem->getLabel());
    }

    public function testReferencePriceWithZeroPurchaseUnit(): void
    {
        $this->createProduct([
            'purchaseUnit' => 0.0,
            'referenceUnit' => 1.0,
            'unit' => [
                'shortCode' => 't',
                'name' => 'test',
            ],
        ]);

        $cart = $this->getProductCart();
        $lineItem = $cart->get($this->ids->get('product'));

        static::assertInstanceOf(QuantityPriceDefinition::class, $lineItem->getPriceDefinition());

        /** @var QuantityPriceDefinition $priceDefinition */
        $priceDefinition = $lineItem->getPriceDefinition();
        static::assertNull($priceDefinition->getReferencePriceDefinition());

        static::assertNull($lineItem->getPrice()->getReferencePrice());
    }

    /**
     * @dataProvider advancedPricingProvider
     */
    public function testAdvancedPricing(bool $valid, float $price): void
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'test'))
            ->price(100)
            ->prices('rule-1', 200, 'default', null, 1, $valid)
            ->visibility()
            ->build();

        $this->getContainer()->get('product.repository')->create([$product], $ids->getContext());

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $result = $this->getContainer()->get(CartRuleLoader::class)
            ->loadByToken($context, Uuid::randomHex());

        $cart = $result->getCart();

        static::assertEquals($valid, \in_array($ids->get('rule-1'), $context->getRuleIds(), true));

        $lineItem = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($ids->get('test'));

        $cart = $this->getContainer()->get(CartService::class)
            ->add($cart, [$lineItem], $context);

        static::assertCount(1, $cart->getLineItems());

        $lineItem = $cart->getLineItems()->first();
        static::assertEquals('product', $lineItem->getType());
        static::assertEquals($ids->get('test'), $lineItem->getReferencedId());

        static::assertEquals($price, $lineItem->getPrice()->getTotalPrice());
    }

    public function advancedPricingProvider()
    {
        yield 'Test not matching rule' => [false, 100];

        yield 'Test matching rule' => [true, 200];
    }

    public function testOverwriteLabelNoPermission(): void
    {
        $this->createProduct();
        $service = $this->getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $context = $this->getContainer()->get(SalesChannelContextService::class)
            ->get(new SalesChannelContextServiceParameters(Defaults::SALES_CHANNEL, $token));

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $product->setLabel('My special product');

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

        $cart = $service->getCart($token, $context);
        $service->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());
        static::assertSame('test', $actualProduct->getLabel());
    }

    /**
     * @group slow
     */
    public function testLineItemPropertiesPurchasePrice(): void
    {
        $this->createProduct();

        $token = $this->ids->create('token');
        $salesChannelContextService = $this->getContainer()->get(SalesChannelContextService::class);
        $context = $salesChannelContextService->get(new SalesChannelContextServiceParameters(Defaults::SALES_CHANNEL, $token, null, Defaults::CURRENCY));
        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($token, $context);
        $product = $this->getContainer()->get(ProductLineItemFactory::class)->create($this->ids->get('product'));
        $cartService->add($cart, $product, $context);

        $productCartProcessor = $this->getContainer()->get(ProductCartProcessor::class);
        $productCartProcessor->collect(
            new CartDataCollection(),
            $cart,
            $context,
            new CartBehavior()
        );

        $payload = $cart->get($product->getId())->getPayload();
        $purchasePrices = json_decode($payload['purchasePrices']);
        static::assertSame(Defaults::CURRENCY, $purchasePrices->currencyId);
        static::assertSame(7.5, $purchasePrices->gross);
        static::assertSame(5, $purchasePrices->net);
        static::assertFalse($purchasePrices->linked);
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
     * @group slow
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

    public function testProcessCartShouldSkipProductStockValidation(): void
    {
        $this->createProduct();
        $service = $this->getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $options = [
            SalesChannelContextService::PERMISSIONS => [ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION => true],
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, Defaults::SALES_CHANNEL, $options);

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $product->setLabel('My special product');

        $cart = $service->getCart($token, $context);
        $service->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());
        static::assertEquals($product->getQuantity(), $actualProduct->getQuantity());
        static::assertEquals($product->getPrice(), $this->calculator->calculate($product->getPriceDefinition(), $context));
        static::assertEquals($product, $actualProduct);
    }

    /**
     * @dataProvider productDeliverabilityProvider
     * @group slow
     */
    public function testProcessCartShouldReturnFixedQuantity(int $minPurchase, int $purchaseSteps, int $maxPurchase, int $quantity, int $quantityExpected, bool $isCheckMessage): void
    {
        $additionalData = [
            'maxPurchase' => $maxPurchase,
            'minPurchase' => $minPurchase,
            'purchaseSteps' => $purchaseSteps,
        ];
        $this->createProduct($additionalData);
        $service = $this->getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $options = [
            SalesChannelContextService::PERMISSIONS => [ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true],
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, Defaults::SALES_CHANNEL, $options);

        $config = [
            'quantity' => $quantity,
        ];
        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'), $config);

        $product->setLabel('My special product');

        $cart = $service->getCart($token, $context);
        $service->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());
        static::assertEquals($quantityExpected, $actualProduct->getQuantity());
        if ($isCheckMessage) {
            static::assertEquals(self::PURCHASE_STEP_QUANTITY_ERROR_KEY, $service->getCart($token, $context)->getErrors()->first()->getMessageKey());
        }
    }

    public function productDeliverabilityProvider()
    {
        return [
            'fixed quantity should be return 2' => [2, 2, 20, 3, 2, true],
            'fixed quantity should be return 4' => [2, 2, 20, 5, 4, true],
            'fixed quantity should be return 3' => [1, 2, 20, 4, 3, true],
            'fixed quantity should be return 9' => [1, 2, 20, 10, 9, true],
            'fixed quantity should be return 5, actual quantity is 6' => [5, 5, 20, 6, 5, true],
            'fixed quantity should be return 5, actual quantity is 7' => [5, 5, 20, 7, 5, true],
            'fixed quantity should be return 5, actual quantity is 8' => [5, 5, 20, 8, 5, true],
            'fixed quantity should be return 5, actual quantity is 9' => [5, 5, 20, 9, 5, true],
            'fixed quantity should be return equal max purchase' => [2, 2, 20, 22, 20, false],
            'fixed quantity should be return equal min purchase' => [2, 2, 20, 1, 2, false],
            'fixed quantity should be return 1' => [1, 3, 5, 2, 1, true],
            'fixed quantity should be return 10 with error message' => [10, 3, 13, 11, 10, true],
            'fixed quantity should be return 10, without error message' => [10, 2, 20, 2, 10, false],
        ];
    }

    public function testProcessCartShouldSetQuantityOfPriceDefinitionWhenAddingASimilarProduct(): void
    {
        $this->createProduct();
        $token = $this->ids->create('token');
        $options = [
            SalesChannelContextService::PERMISSIONS => [
                ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION => false,
                ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
            ],
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, Defaults::SALES_CHANNEL, $options);

        $definition = new QuantityPriceDefinition(10, new TaxRuleCollection(), 1);

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));
        $product->setPriceDefinition($definition);
        $product->setLabel('My test product');
        $product->setQuantity(5);
        $product->setExtensions([
            ProductCartProcessor::CUSTOM_PRICE => true,
        ]);

        $cart = $this->cartService->getCart($token, $context);
        $this->cartService->add($cart, $product, $context);
        $this->cartService->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());

        static::assertEquals($product->getQuantity(), $actualProduct->getQuantity());
        static::assertEquals($product->getPrice(), $this->calculator->calculate($product->getPriceDefinition(), $context));
        static::assertEquals($product, $actualProduct);
    }

    public function testProcessCartShouldReCalculateThePriceWhenAddAProductAndHasNoCustomPrice(): void
    {
        $this->createProduct();
        $token = $this->ids->create('token');
        $options = [
            SalesChannelContextService::PERMISSIONS => [
                ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION => false,
                ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
            ],
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, Defaults::SALES_CHANNEL, $options);

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));
        $product->setLabel('My test product');

        $cart = $this->cartService->getCart($token, $context);
        $this->cartService->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());

        static::assertEquals($product->getQuantity(), $actualProduct->getQuantity());
        static::assertEquals($product->getPrice(), $this->calculator->calculate($product->getPriceDefinition(), $context));
        static::assertEquals($product, $actualProduct);
    }

    public function testProcessCartWithNulledFreeShipping(): void
    {
        $this->createProduct([
            'shippingFree' => null,
        ]);
        $token = $this->ids->create('token');
        $options = [
            SalesChannelContextService::PERMISSIONS => [
                ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION => false,
                ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
            ],
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, Defaults::SALES_CHANNEL, $options);

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));
        $product->setLabel('My test product');

        $cart = $this->cartService->getCart($token, $context);
        $this->cartService->add($cart, $product, $context);

        $actualProduct = $cart->get($product->getId());

        static::assertFalse($actualProduct->getDeliveryInformation()->getFreeDelivery());
    }

    private function getProductCart(): Cart
    {
        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $context = $this->getContext();

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $this->cartService->add($cart, $product, $context);

        return $cart;
    }

    private function getContext(): SalesChannelContext
    {
        $token = $this->ids->create('token');

        return $this->getContainer()->get(SalesChannelContextService::class)
            ->get(new SalesChannelContextServiceParameters(Defaults::SALES_CHANNEL, $token));
    }

    private function createProduct(?array $additionalData = []): void
    {
        $data = [
            'id' => $this->ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'purchasePrices' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 7.5, 'net' => 5, 'linked' => false],
                ['currencyId' => Uuid::randomHex(), 'gross' => 150, 'net' => 100, 'linked' => false],
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
        ];

        $data = array_merge($data, $additionalData);

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
