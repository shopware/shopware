<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class WishlistControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use LineItemTestFixtureBehaviour;
    use StorefrontControllerTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private $customerId;

    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    public function setUp(): void
    {
        parent::setUp();
        $this->customerId = Uuid::randomHex();
        $this->salesChannelContext = $this->createSalesChannelContext();
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    /**
     * @before
     * @after
     */
    public function clearFlashBag(): void
    {
        $this->getFlashBag()->clear();
    }

    public function testWishlistIndex(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);

        // set wishlist enable
        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        // login with customer
        $browser = $this->login();

        $productNumber = $this->createProduct();

        // add product to wishlist
        $this->createCustomerWishlist($this->customerId, $productNumber);

        $browser->request('GET', '/wishlist');
        $response = $browser->getResponse();

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
    }

    public function testDeleteProductInWishlistPage(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);

        // set wishlist enable
        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        // login with customer
        $browser = $this->login();

        $productNumber = $this->createProduct();

        $browser->request('DELETE', '/wishlist/product/delete/' . $productNumber);

        static::assertSame(
            ['danger' => ['Unfortunately, something went wrong.']],
            $this->getFlashBag()->all()
        );

        // add product to wishlist
        $this->createCustomerWishlist($this->customerId, $productNumber);

        $browser->request('DELETE', '/wishlist/product/delete/' . $productNumber);
        $response = $browser->getResponse();

        static::assertArrayHasKey('success', $this->getFlashBag()->all());
        static::assertSame(200, $response->getStatusCode());
    }

    private function createCustomer(): CustomerEntity
    {
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $this->customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'testuser@example.com',
                'password' => 'test',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$this->customerId]), Context::createDefaultContext())->first();
    }

    private function login(): KernelBrowser
    {
        $customer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
                'password' => 'test',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($response->getContext()->getCustomer());

        return $browser;
    }

    private function createProduct(array $config = []): string
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => $id,
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $product = array_replace_recursive($product, $config);

        $repository = $this->getContainer()->get('product.repository');

        $repository->create([$product], Context::createDefaultContext());

        return $id;
    }

    private function createCustomerWishlist(string $customerId, string $productId): string
    {
        $customerWishlistId = Uuid::randomHex();
        $customerWishlistRepository = $this->getContainer()->get('customer_wishlist.repository');

        $customerWishlistRepository->create([
            [
                'id' => $customerWishlistId,
                'customerId' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'products' => [
                    [
                        'productId' => $productId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $customerWishlistId;
    }

    private function getFlashBag(): FlashBagInterface
    {
        return $this->getContainer()->get('session')->getFlashBag();
    }
}
