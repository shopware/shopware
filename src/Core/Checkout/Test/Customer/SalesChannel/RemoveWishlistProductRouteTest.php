<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @group store-api
 */
class RemoveWishlistProductRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var object|null
     */
    private $customerRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new TestDataCollection($this->context);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $email = Uuid::randomHex() . '@example.com';
        $this->customerId = $this->createCustomer('shopware', $email);

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

    public function testDeleteProductShouldReturnSuccess(): void
    {
        $productId = $this->createProduct($this->context);
        $this->createCustomerWishlist($this->context, $this->customerId, $productId);

        $this->browser
            ->request(
                'DELETE',
                '/store-api/customer/wishlist/delete/' . $productId
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);
    }

    public function testDeleteProductShouldThrowCustomerWishlistNotActivatedException(): void
    {
        $productData = $this->createProduct($this->context);
        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $this->browser
            ->request(
                'DELETE',
                '/store-api/customer/wishlist/delete/' . $productData[0]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('CHECKOUT__WISHLIST_IS_NOT_ACTIVATED', $errors['code']);
        static::assertEquals('Forbidden', $errors['title']);
        static::assertEquals('Wishlist is not activated!', $errors['detail']);
    }

    public function testDeleteProductShouldThrowCustomerNotLoggedInException(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Random::getAlphanumericString(12));
        $productId = Uuid::randomHex();

        $this->browser
            ->request(
                'DELETE',
                '/store-api/customer/wishlist/delete/' . $productId
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $errors['code']);
        static::assertEquals('Forbidden', $errors['title']);
        static::assertEquals('Customer is not logged in.', $errors['detail']);
    }

    public function testDeleteProductShouldThrowCustomerWishlistNotFoundException(): void
    {
        $productId = $this->createProduct($this->context);

        $this->browser
            ->request(
                'DELETE',
                '/store-api/customer/wishlist/delete/' . $productId
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $errors = $response['errors'][0];
        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('CHECKOUT__WISHLIST_NOT_FOUND', $errors['code']);
        static::assertEquals('Not Found', $errors['title']);
        static::assertEquals('Wishlist for this customer was not found.', $errors['detail']);
    }

    public function testDeleteProductShouldThrowWishlistProductNotFoundException(): void
    {
        $productId = $this->createProduct($this->context);
        $this->createCustomerWishlist($this->context, $this->customerId, $productId);

        $productId = Uuid::randomHex();
        $this->browser
            ->request(
                'DELETE',
                '/store-api/customer/wishlist/delete/' . $productId
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $errors = $response['errors'][0];
        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('CHECKOUT__WISHLIST_PRODUCT_NOT_FOUND', $errors['code']);
        static::assertEquals('Not Found', $errors['title']);
        static::assertEquals('Wishlist product with id ' . $productId . ' not found', $errors['detail']);
    }

    private function createProduct(Context $context): string
    {
        $productId = Uuid::randomHex();

        $productNumber = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => $productNumber,
            'stock' => 1,
            'name' => 'Test Product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $this->getSalesChannelApiSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }

    private function createCustomerWishlist(Context $context, string $customerId, string $productId): string
    {
        $customerWishlistId = Uuid::randomHex();
        $customerWishlistRepository = $this->getContainer()->get('customer_wishlist.repository');

        $customerWishlistRepository->create([
            [
                'id' => $customerWishlistId,
                'customerId' => $customerId,
                'salesChannelId' => $this->getSalesChannelApiSalesChannelId(),
                'products' => [
                    [
                        'productId' => $productId,
                    ],
                ],
            ],
        ], $context);

        return $customerWishlistId;
    }
}
