<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @group store-api
 */
class ShippingMethodRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

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
            ->update($updateData, $this->ids->context);
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $ids = array_column($response['elements'], 'id');

        static::assertSame(3, $response['total']);
        static::assertContains($this->ids->get('shipping'), $ids);
        static::assertContains($this->ids->get('shipping2'), $ids);
        static::assertEmpty($response['elements'][0]['availabilityRule']);
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(3, $response['total']);
        static::assertNotEmpty($response['elements'][0]['availabilityRule']);
    }

    public function testOnlyAvailable(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method?onlyAvailable=1',
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(2, $response['total']);
        static::assertCount(2, $response['elements']);
        static::assertNotContains($this->ids->get('shipping3'), array_column($response['elements'], 'id'));
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('shipping'),
                'active' => true,
                'bindShippingfree' => false,
                'name' => 'test',
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
                'bindShippingfree' => false,
                'name' => 'test',
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
                'bindShippingfree' => false,
                'name' => 'test',
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
            ->create($data, $this->ids->context);
    }
}
