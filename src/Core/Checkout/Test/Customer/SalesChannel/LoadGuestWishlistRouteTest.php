<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadGuestWishlistRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadGuestWishlistRouteResponse;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadGuestWishlistRouteTest extends TestCase
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
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var EventDispatcherInterface|null
     */
    private $eventDispatcher;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);

        $this->context = Context::createDefaultContext();
        $this->ids = new TestDataCollection($this->context);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create(Uuid::randomHex(), $this->ids->get('sales-channel'));

        /* @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.cart.wishlistEnabled', true);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
    }

    public function testLoadWishlistProductsSuccessViaHttp(): void
    {
        $productId = $this->createProduct($this->context);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/guest/wishlist',
                ['productIds' => [$productId]]
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertNotEmpty($response);

        static::assertNotEmpty($response);
        static::assertEquals(1, $response['total']);
        static::assertNotNull($response['elements']);
    }

    public function testLoadWishlistProductsSuccessViaRoute(): void
    {
        $productId = $this->createProduct($this->context);

        /** @var LoadGuestWishlistRoute $guestWishlistLoadRoute */
        $guestWishlistLoadRoute = $this->getContainer()->get(LoadGuestWishlistRoute::class);

        $request = new Request();
        $request->attributes->set('productIds', [$productId]);

        $response = $guestWishlistLoadRoute->load($request, $this->salesChannelContext, new Criteria());

        static::assertInstanceOf(LoadGuestWishlistRouteResponse::class, $response);
        static::assertInstanceOf(EntitySearchResult::class, $result = $response->getResult());
        static::assertCount(1, $result);
        static::assertEquals($productId, $result->first()->get('id'));
    }

    public function testLoadWishlistProductsErrorWithInvalidProductIds(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/guest/wishlist',
                ['productIds' => 'invalid']
            );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $errors = $response['errors'][0];
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $this->browser->getResponse()->getStatusCode());
        static::assertEquals('Argument $productIds is not an array', $errors['detail']);
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
}
