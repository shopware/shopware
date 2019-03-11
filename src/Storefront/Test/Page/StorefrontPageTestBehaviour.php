<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Exception\MissingParameterException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;

trait StorefrontPageTestBehaviour
{
    public static function assertPageEvent(
        string $expectedClass,
        Event $event,
        CheckoutContext $checkoutContext,
        InternalRequest $request,
        Struct $page
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($checkoutContext, $event->getCheckoutContext());
        TestCase::assertSame($checkoutContext->getContext(), $event->getContext());
        TestCase::assertSame($request, $event->getRequest());
        TestCase::assertSame($page, $event->getPage());
    }

    abstract protected function getPageLoader(): PageLoaderInterface;

    protected function assertFailsWithoutNavigation(): void
    {
        $request = new InternalRequest();
        $context = $this->createCheckoutContext();

        $this->expectNavigationMissingException();
        $this->getPageLoader()->load($request, $context);
    }

    protected function assertLoginRequirement(array $queryParams = []): void
    {
        $request = new InternalRequest($queryParams);
        $context = $this->createCheckoutContextWithNavigation();
        $this->expectException(CustomerNotLoggedInException::class);
        $this->getPageLoader()->load($request, $context);
    }

    protected function expectNavigationMissingException()
    {
        $this->expectParamMissingException('navigationId');
    }

    protected function expectParamMissingException(string $paramName)
    {
        TestCase::expectException(MissingParameterException::class);
        TestCase::expectExceptionMessage('Parameter "' . $paramName . '" is missing');
    }

    protected function placeRandomOrder(CheckoutContext $context): string
    {
        $product = $this->getRandomProduct($context);

        $lineItem = (new LineItem($product->getId(), ProductCollector::LINE_ITEM_TYPE, 1))
                    ->setPayload(['id' => $product->getId()])
                    ->setRemovable(true)
                    ->setStackable(true);

        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($context->getToken(), $context);
        $cart->add($lineItem);

        return $cartService->order($cart, $context);
    }

    protected function getRandomProduct(CheckoutContext $context): ProductEntity
    {
        $id = Uuid::uuid4()->getHex();
        $productRepository = $this->getContainer()->get('product.repository');
        $productVisibilityRepository = $this->getContainer()->get('product_visibility.repository');

        $data = [
            'id' => $id,
            'name' => StorefrontPageTestConstants::PRODUCT_NAME,
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
            'categories' => [
                ['id' => Uuid::uuid4()->getHex(), 'name' => 'asd'],
            ],
        ];

        $productRepository->create([$data], $context->getContext());
        $productVisibilityRepository->create([[
            'productId' => $id,
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ]], $context->getContext());

        /** @var StorefrontProductRepository $storefrontProductRepository */
        $storefrontProductRepository = $this->getContainer()->get(StorefrontProductRepository::class);
        $searchResult = $storefrontProductRepository->search(new Criteria([$id]), $context);

        return $searchResult->first();
    }

    protected function createCheckoutContextWithNavigation(): CheckoutContext
    {
        $data = [
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'navigationId' => $this->getNavigationId(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => Defaults::SNIPPET_BASE_SET_EN,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => Defaults::COUNTRY,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => Defaults::PAYMENT_METHOD_DEBIT]],
            'shippingMethods' => [['id' => Defaults::SHIPPING_METHOD]],
            'countries' => [['id' => Defaults::COUNTRY]],
        ];

        return $this->createContext($data, []);
    }

    protected function createCheckoutContextWithLoggedInCustomerAndWithNavigation(): CheckoutContext
    {
        $data = [
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'navigationId' => $this->getNavigationId(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => Defaults::SNIPPET_BASE_SET_EN,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => Defaults::COUNTRY,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => Defaults::PAYMENT_METHOD_DEBIT]],
            'shippingMethods' => [['id' => Defaults::SHIPPING_METHOD]],
            'countries' => [['id' => Defaults::COUNTRY]],
        ];

        return $this->createContext($data, [
            CheckoutContextService::CUSTOMER_ID => $this->createCustomer()->getId(),
        ]);
    }

    protected function createCheckoutContext(): CheckoutContext
    {
        $data = [
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => Defaults::SNIPPET_BASE_SET_EN,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => Defaults::COUNTRY,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => Defaults::PAYMENT_METHOD_DEBIT]],
            'shippingMethods' => [['id' => Defaults::SHIPPING_METHOD]],
            'countries' => [['id' => Defaults::COUNTRY]],
        ];

        return $this->createContext($data, []);
    }

    protected function catchEvent(string $eventName, &$eventResult): void
    {
        $this->getContainer()->get('event_dispatcher')->addListener($eventName, function ($event) use (&$eventResult) {
            $eventResult = $event;
        });
    }

    abstract protected function getContainer(): ContainerInterface;

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $data = [
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
                    'salutation' => 'Mr.',
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'foo@bar.de',
                'password' => 'password',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutation' => 'Mr.',
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

    private function createContext(array $salesChannel, array $options): CheckoutContext
    {
        $factory = $this->getContainer()->get(CheckoutContextFactory::class);
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $salesChannelId = Uuid::uuid4()->getHex();
        $salesChannel['id'] = $salesChannelId;

        $salesChannelRepository->create([$salesChannel], Context::createDefaultContext());

        return $factory
            ->create(Uuid::uuid4()->getHex(), $salesChannelId, $options);
    }

    private function getNavigationId(): string
    {
        $navigationRepo = $this->getContainer()->get('navigation.repository');

        return $navigationRepo->search(new Criteria(), Context::createDefaultContext())->first()->getId();
    }
}
