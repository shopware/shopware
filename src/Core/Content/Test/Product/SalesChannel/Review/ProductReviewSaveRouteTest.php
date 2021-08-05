<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Review;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group store-api
 */
class ProductReviewSaveRouteTest extends TestCase
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
        ]);

        $this->setVisibilities();
    }

    public function testRequiresLogin(): void
    {
        $this->browser->request('POST', $this->getUrl());

        $response = $this->browser->getResponse();

        static::assertEquals(403, $response->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals($response['errors'][0]['code'], 'CHECKOUT__CUSTOMER_NOT_LOGGED_IN');
    }

    public function testCreate(): void
    {
        $this->login();

        $this->assertReviewCount(0);

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        $response = $this->browser->getResponse();
        $content = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals(204, $response->getStatusCode(), print_r($content, true));

        $this->assertReviewCount(1);
    }

    public function testUpdate(): void
    {
        $this->login();

        $this->assertReviewCount(0);

        $id = Uuid::randomHex();

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        $response = $this->browser->getResponse();
        $content = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals(204, $response->getStatusCode(), print_r($content, true));

        $this->assertReviewCount(1);

        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);
        $this->assertReviewCount(1);
    }

    public function testValidation(): void
    {
        $this->login();

        $this->browser->request('POST', $this->getUrl());

        $response = $this->browser->getResponse();

        static::assertEquals(400, $response->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals($response['errors'][0]['source']['pointer'], '/title');
        static::assertEquals($response['errors'][1]['source']['pointer'], '/content');
    }

    private function assertReviewCount(int $expected): void
    {
        $count = $this->getContainer()
            ->get(Connection::class)
            ->fetchColumn('SELECT COUNT(*) FROM product_review WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($this->ids->get('product'))]);

        static::assertEquals($expected, $count);
    }

    private function login(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer('shopware', $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    private function createCustomer(string $password, ?string $email = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->getContainer()->get('customer.repository')->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => Defaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Fooo',
                'lastName' => 'Barr',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], $this->ids->context);

        return $customerId;
    }

    private function createData(): void
    {
        $product = [
            'id' => $this->ids->create('product'),
            'manufacturer' => ['id' => $this->ids->create('manufacturer-'), 'name' => 'test-'],
            'productNumber' => $this->ids->get('product'),
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $update = [
            [
                'id' => $this->ids->get('product'),
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')
            ->update($update, $this->ids->context);
    }

    private function getUrl()
    {
        return '/store-api/product/' . $this->ids->get('product') . '/review';
    }
}
