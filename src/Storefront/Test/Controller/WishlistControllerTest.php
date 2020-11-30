<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPage;
use Shopware\Storefront\Page\Wishlist\WishlistPage;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class WishlistControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private $customerId;

    public function setUp(): void
    {
        parent::setUp();
        $this->customerId = Uuid::randomHex();
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.cart.wishlistEnabled', true);
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
        $browser = $this->login();

        $salesChannelId = $browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        $productId = $this->createProduct($salesChannelId);

        // add product to wishlist
        $this->createCustomerWishlist($productId, $salesChannelId);

        $browser->request('GET', '/wishlist');
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertInstanceOf(WishlistPage::class, $response->getData()['page']);
    }

    public function testWishlistGuestIndex(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());

        $browser->request('GET', $_SERVER['APP_URL'] . '/wishlist');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertInstanceOf(GuestWishlistPage::class, $response->getData()['page']);
    }

    public function testWishlistGuestPageletShouldThrowExceptionWhenLoggedIn(): void
    {
        $browser = $this->login();

        $browser->request('GET', $_SERVER['APP_URL']);

        $productId = $this->createProduct($browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

        $browser->request('POST', $_SERVER['APP_URL'] . '/wishlist/guest-pagelet', $this->tokenize('frontend.wishlist.guestPage.pagelet', ['productIds' => [$productId]]));

        static::assertEquals(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    public function testWishlistGuestPagelet(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());

        $browser->request('GET', $_SERVER['APP_URL']);

        $productId = $this->createProduct($browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

        $browser->request('POST', $_SERVER['APP_URL'] . '/wishlist/guest-pagelet', $this->tokenize('frontend.wishlist.guestPage.pagelet', ['productIds' => [$productId]]));
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertInstanceOf(EntitySearchResult::class, $result = $response->getData()['searchResult']);
        static::assertCount(1, $result);
        static::assertEquals($productId, $result->first()->get('id'));
    }

    public function testDeleteProductInWishlistPage(): void
    {
        $browser = $this->login();

        $salesChannelId = $browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        $productId = $this->createProduct($salesChannelId);

        $browser->request('DELETE', '/wishlist/product/delete/' . $productId);

        static::assertSame(
            ['danger' => ['Unfortunately, something went wrong.']],
            $this->getFlashBag()->all()
        );

        $this->createCustomerWishlist($productId, $salesChannelId);

        $browser->request('DELETE', '/wishlist/product/delete/' . $productId);
        $response = $browser->getResponse();

        static::assertArrayHasKey('success', $this->getFlashBag()->all());
        static::assertSame(200, $response->getStatusCode());
    }

    public function testAjaxListWithoutCreatedWishlist(): void
    {
        $browser = $this->login();

        $browser->request('GET', $_SERVER['APP_URL'] . '/wishlist/list');

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());
        static::assertEmpty(json_decode($response->getContent()));
    }

    public function testAjaxAdd(): void
    {
        $browser = $this->login();

        $productId = $this->createProduct($browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

        $browser->request('POST', $_SERVER['APP_URL'] . '/wishlist/add/' . $productId, $this->tokenize('frontend.wishlist.product.add', []));

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertTrue($content['success']);
    }

    public function testAjaxList(): void
    {
        $browser = $this->login();

        $browser->request('GET', $_SERVER['APP_URL'] . '/wishlist/list');

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());
    }

    public function testAjaxRemove(): void
    {
        $browser = $this->login();

        $productId = $this->createProduct($browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

        $browser->request('POST', $_SERVER['APP_URL'] . '/wishlist/add/' . $productId, $this->tokenize('frontend.wishlist.product.add', []));

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('POST', $_SERVER['APP_URL'] . '/wishlist/remove/' . $productId, $this->tokenize('frontend.wishlist.product.remove', []));

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        static::assertNotEmpty($content);
        static::assertTrue($content['success']);
    }

    public function testAddAfterLogin(): void
    {
        $browser = $this->login();

        $productId = $this->createProduct($browser->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

        $browser->request('GET', $_SERVER['APP_URL'] . '/wishlist/add-after-login/' . $productId);

        /** @var RedirectResponse $response */
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/', $response->getTargetUrl());

        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->getContainer()->get('session')->getFlashBag();

        static::assertNotEmpty($successFlash = $flashBag->get('success'));
        static::assertEquals('You have successfully added the product into the wishlist.', $successFlash[0]);

        $browser->request('GET', $_SERVER['APP_URL'] . '/wishlist/add-after-login/' . $productId);

        static::assertNotEmpty($warningFlash = $flashBag->get('warning'));
        static::assertEquals('Product with id ' . $productId . ' already added in wishlist', $warningFlash[0]);
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

    private function createProduct(?string $salesChannelId = null, array $config = []): string
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
                    'salesChannelId' => $salesChannelId ?? Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $product = array_replace_recursive($product, $config);

        $repository = $this->getContainer()->get('product.repository');

        $repository->create([$product], Context::createDefaultContext());

        return $id;
    }

    private function createCustomerWishlist(string $productId, string $salesChannelId): string
    {
        $customerWishlistId = Uuid::randomHex();
        $customerWishlistRepository = $this->getContainer()->get('customer_wishlist.repository');

        $customerWishlistRepository->create([
            [
                'id' => $customerWishlistId,
                'customerId' => $this->customerId,
                'salesChannelId' => $salesChannelId,
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
