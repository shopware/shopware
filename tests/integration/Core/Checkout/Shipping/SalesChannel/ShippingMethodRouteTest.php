<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\SortedShippingMethodRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('store-api')]
class ShippingMethodRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'shippingMethodId' => $this->ids->get('shipping'),
        ]);

        $updateData = [
            [
                'id' => $this->ids->get('shipping'),
                'salesChannels' => [
                    [
                        'id' => $this->ids->get('sales-channel'),
                    ],
                ],
            ],
            [
                'id' => $this->ids->get('shipping2'),
                'salesChannels' => [
                    [
                        'id' => $this->ids->get('sales-channel'),
                    ],
                ],
            ],
            [
                'id' => $this->ids->get('shipping3'),
                'salesChannels' => [
                    [
                        'id' => $this->ids->get('sales-channel'),
                    ],
                ],
            ],
        ];

        static::getContainer()->get('shipping_method.repository')
            ->update($updateData, Context::createDefaultContext());

        $this->salesChannelContext = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testLoad(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        $ids = array_column($response['elements'], 'id');

        static::assertSame(3, $response['total']);
        static::assertContains($this->ids->get('shipping'), $ids);
        static::assertContains($this->ids->get('shipping2'), $ids);
        static::assertEmpty($response['elements'][0]['availabilityRule']);

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(ShippingMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testSortOrderWithDefault(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        $ids = array_column($response['elements'], 'id');

        static::assertEquals(
            [
                $this->ids->get('shipping'),    // position  1 (selected method & sales-channel default)
                $this->ids->get('shipping3'),   // position -3
                $this->ids->get('shipping2'),   // position  5
            ],
            $ids
        );
    }

    public function testSortOrderWithSelectedShippingMethod(): void
    {
        $this->browser->request(
            'PATCH',
            '/store-api/context',
            ['shippingMethodId' => $this->ids->get('shipping2')]
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        $ids = array_column($response['elements'], 'id');

        static::assertEquals(
            Feature::isActive('ACCESSIBILITY_TWEAKS') ?
            [
                $this->ids->get('shipping'),    // position  1 (sales-channel default)
                $this->ids->get('shipping3'),   // position -3
                $this->ids->get('shipping2'),   // position  5 (selected method)
            ] : [
                $this->ids->get('shipping2'),   // position  5 (selected method)
                $this->ids->get('shipping'),    // position  1 (sales-channel default)
                $this->ids->get('shipping3'),   // position -3
            ],
            $ids
        );
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed due to behavior change
     */
    public function testSorting(): void
    {
        Feature::skipTestIfActive('ACCESSIBILITY_TWEAKS', $this);

        $shippingMethodRoute = static::getContainer()->get(ShippingMethodRoute::class);

        $request = new Request();

        $unselectedPaymentResult = $shippingMethodRoute->load($request, $this->salesChannelContext, new Criteria());
        $lastPaymentMethodId = $unselectedPaymentResult->getShippingMethods()->last()?->getId() ?? '';

        $this->salesChannelContext->getShippingMethod()->setId($lastPaymentMethodId);
        $selectedPaymentMethodResult = $shippingMethodRoute->load($request, $this->salesChannelContext, new Criteria());

        static::assertInstanceOf(SortedShippingMethodRoute::class, $shippingMethodRoute);
        static::assertSame($lastPaymentMethodId, $selectedPaymentMethodResult->getShippingMethods()->first()?->getId());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(ShippingMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testOnlyAvailableExcludesShippingMethodsWithoutAnyPrice(): void
    {
        $this->createShippingMethodWithPrices('shipping4', []);

        static::assertContains($this->ids->get('shipping4'), $this->requestShippingMethodIds(false));

        $available = $this->requestShippingMethodIds(true);

        static::assertNotContains($this->ids->get('shipping4'), $available);
        static::assertCount(2, $available);
    }

    public function testOnlyAvailableExcludesShippingMethodsWhoseOnlyPricesHaveNoCurrencyValues(): void
    {
        $this->createShippingMethodWithPrices('shipping4', [
            ['id' => $this->ids->create('empty-price'), 'calculation' => 1, 'quantityStart' => 1],
        ]);

        static::assertContains($this->ids->get('shipping4'), $this->requestShippingMethodIds(false));

        static::assertNotContains($this->ids->get('shipping4'), $this->requestShippingMethodIds(true));
    }

    public function testOnlyAvailableKeepsShippingMethodsWithAMixOfUsableAndEmptyPrices(): void
    {
        // A nullable field on one row must not turn the existence check into an anti-join
        static::getContainer()->get('shipping_method_price.repository')->create([[
            'id' => $this->ids->create('mixed-empty-price'),
            'shippingMethodId' => $this->ids->get('shipping'),
            'calculation' => 1,
            'quantityStart' => 2,
        ]], Context::createDefaultContext());

        static::assertContains($this->ids->get('shipping'), $this->requestShippingMethodIds(true));
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                    'includes' => [
                        'shipping_method' => [
                            'name',
                        ],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('name', $response['elements'][0]);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
    }

    public function testAssociations(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                    'associations' => [
                        'availabilityRule' => [],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame(3, $response['total']);
        static::assertNotEmpty($response['elements'][0]['availabilityRule']);
    }

    public function testOnlyAvailableGet(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/shipping-method?onlyAvailable=1',
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame(2, $response['total']);
        static::assertCount(2, $response['elements']);
        static::assertNotContains($this->ids->get('shipping3'), array_column($response['elements'], 'id'));

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(ShippingMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testOnlyAvailablePost(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                ['onlyAvailable' => 1],
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame(2, $response['total']);
        static::assertCount(2, $response['elements']);
        static::assertNotContains($this->ids->get('shipping3'), array_column($response['elements'], 'id'));

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(ShippingMethodRouteHook::HOOK_NAME, $traces);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvingPrice(string $key): array
    {
        return [
            'id' => $this->ids->create($key),
            'calculation' => 1,
            'quantityStart' => 1,
            'currencyPrice' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 10,
                    'gross' => 11,
                    'linked' => false,
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $prices
     */
    private function createShippingMethodWithPrices(string $key, array $prices): void
    {
        static::getContainer()->get('shipping_method.repository')->create([[
            'id' => $this->ids->create($key),
            'active' => true,
            'position' => 10,
            'bindShippingfree' => false,
            'name' => 'test',
            'technicalName' => 'shipping_test_' . $key,
            'prices' => $prices,
            'availabilityRuleId' => $this->ids->get('rule'),
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'testDeliveryTime',
                'min' => 1,
                'max' => 90,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
            'salesChannels' => [['id' => $this->ids->get('sales-channel')]],
        ]], Context::createDefaultContext());
    }

    /**
     * @return list<string>
     */
    private function requestShippingMethodIds(bool $onlyAvailable): array
    {
        $this->browser->request('POST', '/store-api/shipping-method', $onlyAvailable ? ['onlyAvailable' => true] : []);

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        return array_column($response['elements'], 'id');
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('shipping'),
                'active' => true,
                'prices' => [$this->resolvingPrice('shipping-price')],
                'position' => 1,
                'bindShippingfree' => false,
                'name' => 'test',
                'technicalName' => 'shipping_test',
                'availabilityRule' => [
                    'id' => $this->ids->create('rule'),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2099-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
                'deliveryTime' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'testDeliveryTime',
                    'min' => 1,
                    'max' => 90,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
            ],
            [
                'id' => $this->ids->create('shipping2'),
                'active' => true,
                'prices' => [$this->resolvingPrice('shipping2-price')],
                'position' => 5,
                'bindShippingfree' => false,
                'name' => 'test',
                'technicalName' => 'shipping_test2',
                'availabilityRule' => [
                    'id' => $this->ids->create('rule2'),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2099-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
                'deliveryTime' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'testDeliveryTime',
                    'min' => 1,
                    'max' => 90,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
            ],
            [
                'id' => $this->ids->create('shipping3'),
                'active' => true,
                'prices' => [$this->resolvingPrice('shipping3-price')],
                'position' => -3,
                'bindShippingfree' => false,
                'name' => 'test',
                'technicalName' => 'shipping_test3',
                'availabilityRule' => [
                    'id' => $this->ids->create('rule3'),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51+02:00',
                                'toDate' => '2000-06-07T11:37:51+02:00',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
                'deliveryTime' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'testDeliveryTime',
                    'min' => 1,
                    'max' => 90,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
            ],
        ];

        static::getContainer()->get('shipping_method.repository')
            ->create($data, Context::createDefaultContext());
    }
}
