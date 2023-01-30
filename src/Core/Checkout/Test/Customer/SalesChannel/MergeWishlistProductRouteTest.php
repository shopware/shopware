<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
class MergeWishlistProductRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private Context $context;

    private string $customerId;

    private SystemConfigService $systemConfigService;

    private EntityRepository $wishlistProductRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);

        $this->wishlistProductRepository = $this->getContainer()->get('customer_wishlist_product.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->customerId = $this->createCustomer('shopware', $email);

        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testMergeProductShouldReturnSuccessNoWishlistExisted(): void
    {
        $productData = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => $productData,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertSame($productData, $wishlistProduct->getEntities()->first()->getProductId());
    }

    public function testMergeTwoProductShouldReturnSuccessNoWishlistExisted(): void
    {
        $productDataOne = $this->createProduct($this->context);
        $productDataTwo = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        $productDataOne, $productDataTwo,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertCount(2, $wishlistProduct->getEntities());
    }

    public function testMergeThreeProductShouldReturnSuccessNoWishlistExisted(): void
    {
        $productDataOne = $this->createProduct($this->context);
        $productDataTwo = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        $productDataOne,
                        $productDataTwo,
                        Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertCount(2, $wishlistProduct->getEntities());
    }

    public function testMergeProductShouldThrowCustomerNotLoggedInException(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Random::getAlphanumericString(12));

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $errors['code']);
        static::assertEquals('Forbidden', $errors['title']);
        static::assertEquals('Customer is not logged in.', $errors['detail']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertNull($wishlistProduct->getEntities()->first());
    }

    public function testMergeProductShouldThrowCustomerWishlistNotActivatedException(): void
    {
        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $errors = $response['errors'][0];
        static::assertSame(403, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('CHECKOUT__WISHLIST_IS_NOT_ACTIVATED', $errors['code']);
        static::assertEquals('Forbidden', $errors['title']);
        static::assertEquals('Wishlist is not activated!', $errors['detail']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertNull($wishlistProduct->getEntities()->first());
    }

    public function testMergeProductShouldSuccessWithNoProductInsert(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => Uuid::randomHex(),
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertNull($wishlistProduct->getEntities()->first());
    }

    public function testMergeProductShouldReturnSuccessAlreadyWishlistExisted(): void
    {
        $productData = $this->createProduct($this->context);
        $this->createCustomerWishlist($productData);

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => $productData,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertSame($productData, $wishlistProduct->getEntities()->first()->getProductId());
    }

    public function testMergeProductShouldReturnSuccessAlreadyProductWishlistExisted(): void
    {
        $alreadyProductData = $this->createProduct($this->context);
        $this->createCustomerWishlist($alreadyProductData);
        $newProductData = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => $newProductData,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertCount(2, $wishlistProduct->getEntities());
    }

    public function testMergeProductShouldReturnSuccessSameProductWishlistExisted(): void
    {
        $alreadyProductData = $this->createProduct($this->context);
        $this->createCustomerWishlist($alreadyProductData);

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [
                        'id' => $alreadyProductData,
                    ],
                ]
            );
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertCount(1, $wishlistProduct->getEntities());
        static::assertSame($alreadyProductData, $wishlistProduct->getEntities()->first()->getProductId());
    }

    public function testMergeProductsWithEmptyWishlistAndEmptyMergeRequest(): void
    {
        $this->createCustomerWishlist();

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertCount(0, $wishlistProduct->getEntities());
    }

    public function testMergeProductsWithNonEmptyWishlistAndEmptyMergeRequest(): void
    {
        $productData = $this->createProduct($this->context);
        $this->createCustomerWishlist($productData);

        $this->browser
            ->request(
                'POST',
                '/store-api/customer/wishlist/merge',
                [
                    'productIds' => [],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);

        $wishlistProduct = $this->wishlistProductRepository->search(new Criteria(), $this->context);
        static::assertNotNull($wishlistProduct);
        static::assertCount(1, $wishlistProduct->getEntities());
    }

    private function createProduct(Context $context): string
    {
        $productId = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
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

    private function createCustomerWishlist(?string $productId = null): string
    {
        $customerWishlistId = Uuid::randomHex();
        $customerWishlistRepository = $this->getContainer()->get('customer_wishlist.repository');

        $data = [
            'id' => $customerWishlistId,
            'customerId' => $this->customerId,
            'salesChannelId' => $this->getSalesChannelApiSalesChannelId(),
        ];

        if ($productId !== null) {
            $data['products'] = [
                [
                    'productId' => $productId,
                ],
            ];
        }

        $customerWishlistRepository->create([$data], $this->context);

        return $customerWishlistId;
    }
}
