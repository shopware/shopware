<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\SortedShippingMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

    private TestDataCollection $ids;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

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

        $this->getContainer()->get('shipping_method.repository')
            ->update($updateData, Context::createDefaultContext());

        $this->salesChannelContext = $this->getContainer()
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

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
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
            [
                $this->ids->get('shipping2'),   // position  5 (selected method)
                $this->ids->get('shipping'),    // position  1 (sales-channel default)
                $this->ids->get('shipping3'),   // position -3
            ],
            $ids
        );
    }

    public function testSorting(): void
    {
        $shippingMethodRoute = $this->getContainer()->get(ShippingMethodRoute::class);

        $request = new Request();

        $unselectedPaymentResult = $shippingMethodRoute->load($request, $this->salesChannelContext, new Criteria());
        $lastPaymentMethodId = $unselectedPaymentResult->getShippingMethods()->last()?->getId() ?? '';

        $this->salesChannelContext->getShippingMethod()->setId($lastPaymentMethodId);
        $selectedPaymentMethodResult = $shippingMethodRoute->load($request, $this->salesChannelContext, new Criteria());

        static::assertInstanceOf(SortedShippingMethodRoute::class, $shippingMethodRoute);
        static::assertSame($lastPaymentMethodId, $selectedPaymentMethodResult->getShippingMethods()->first()?->getId());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(ShippingMethodRouteHook::HOOK_NAME, $traces);
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

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
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

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(ShippingMethodRouteHook::HOOK_NAME, $traces);
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('shipping'),
                'active' => true,
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

        $this->getContainer()->get('shipping_method.repository')
            ->create($data, Context::createDefaultContext());
    }
}
