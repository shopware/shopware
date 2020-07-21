<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

class ShippingMethodRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

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
        ];

        $this->getContainer()->get('shipping_method.repository')
            ->update($updateData, $this->ids->context);
    }

    public function testLoad(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/shipping-method',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $ids = array_column($response, 'id');

        static::assertCount(2, $response);
        static::assertContains($this->ids->get('shipping'), $ids);
        static::assertContains($this->ids->get('shipping2'), $ids);
        static::assertEmpty($response[0]['availabilityRule']);
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/shipping-method',
                [
                    'includes' => [
                        'shipping_method' => [
                            'name',
                        ],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(2, $response);
        static::assertArrayHasKey('name', $response[0]);
        static::assertArrayNotHasKey('id', $response[0]);
    }

    public function testAssociations(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/shipping-method',
                [
                    'associations' => [
                        'availabilityRule' => [],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(2, $response);
        static::assertNotEmpty($response[0]['availabilityRule']);
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
