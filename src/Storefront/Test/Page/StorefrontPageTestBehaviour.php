<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Page\PageLoadedEvent;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Shopware\Tests\Integration\Storefront\Page\StorefrontPageTestConstants;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal in v6.7.0
 */
trait StorefrontPageTestBehaviour
{
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @template TEvent of PageLoadedEvent
     *
     * @param class-string<TEvent> $expectedClass
     * @param TEvent|null $event
     */
    public static function assertPageEvent(
        string $expectedClass,
        ?PageLoadedEvent $event,
        SalesChannelContext $salesChannelContext,
        Request $request,
        Struct $page
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($salesChannelContext, $event->getSalesChannelContext());
        TestCase::assertSame($salesChannelContext->getContext(), $event->getContext());
        TestCase::assertSame($request, $event->getRequest());
        TestCase::assertSame($page, $event->getPage());
    }

    /**
     * @template TEvent of PageletLoadedEvent
     *
     * @param class-string<TEvent> $expectedClass
     * @param TEvent $event
     */
    public static function assertPageletEvent(
        string $expectedClass,
        PageletLoadedEvent $event,
        SalesChannelContext $salesChannelContext,
        Request $request,
        Struct $page
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($salesChannelContext, $event->getSalesChannelContext());
        TestCase::assertSame($salesChannelContext->getContext(), $event->getContext());
        TestCase::assertSame($request, $event->getRequest());
        TestCase::assertSame($page, $event->getPagelet());
    }

    abstract protected function getPageLoader();

    protected function expectParamMissingException(string $paramName): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Parameter "' . $paramName . '" is missing');
    }

    protected function placeRandomOrder(SalesChannelContext $context): string
    {
        $product = $this->getRandomProduct($context);

        $lineItem = (new LineItem($product->getId(), LineItem::PRODUCT_LINE_ITEM_TYPE, $product->getId()))
            ->setRemovable(true)
            ->setStackable(true);

        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($context->getToken(), $context);
        $cart->add($lineItem);

        return $cartService->order($cart, $context, new RequestDataBag());
    }

    /**
     * @param array<int|string, mixed> $config
     */
    protected function getRandomProduct(SalesChannelContext $context, ?int $stock = 1, ?bool $isCloseout = false, array $config = []): ProductEntity
    {
        $id = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $productRepository = $this->getContainer()->get('product.repository');

        $data = [
            'id' => $id,
            'productNumber' => $productNumber,
            'stock' => $stock,
            'name' => StorefrontPageTestConstants::PRODUCT_NAME,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 15],
            'active' => true,
            'isCloseout' => $isCloseout,
            'categories' => [
                ['id' => Uuid::randomHex(), 'name' => 'asd'],
            ],
            'visibilities' => [
                ['salesChannelId' => $context->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $data = array_merge_recursive($data, $config);

        $productRepository->create([$data], $context->getContext());
        $this->addTaxDataToSalesChannel($context, $data['tax']);

        /** @var SalesChannelRepository<ProductCollection> $storefrontProductRepository */
        $storefrontProductRepository = $this->getContainer()->get('sales_channel.product.repository');
        $product = $storefrontProductRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertNotNull($product);

        return $product;
    }

    protected function createSalesChannelContextWithNavigation(): SalesChannelContext
    {
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getAvailableShippingMethod()->getId();
        $countryId = $this->getValidCountryId();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $data = [
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $snippetSetId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'countries' => [['id' => $countryId]],
            'domains' => [
                ['url' => 'http://test.com/' . Uuid::randomHex(), 'currencyId' => Defaults::CURRENCY, 'languageId' => Defaults::LANGUAGE_SYSTEM, 'snippetSetId' => $snippetSetId],
            ],
        ];

        return $this->createContext($data, []);
    }

    protected function createSalesChannelContextWithLoggedInCustomerAndWithNavigation(): SalesChannelContext
    {
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getAvailableShippingMethod()->getId();
        $countryId = $this->getValidCountryId();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $data = [
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $snippetSetId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'countries' => [['id' => $countryId]],
            'domains' => [
                ['url' => 'http://test.com/' . Uuid::randomHex(), 'currencyId' => Defaults::CURRENCY, 'languageId' => Defaults::LANGUAGE_SYSTEM, 'snippetSetId' => $snippetSetId],
            ],
        ];

        return $this->createContext($data, [
            SalesChannelContextService::CUSTOMER_ID => $this->createCustomer()->getId(),
        ]);
    }

    /**
     * @param array<string, mixed>|null $salesChannelData
     */
    protected function createSalesChannelContext(?array $salesChannelData = null): SalesChannelContext
    {
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getAvailableShippingMethod()->getId();
        $countryId = $this->getValidCountryId();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $data = [
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $snippetSetId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'countries' => [['id' => $countryId]],
            'domains' => [
                ['url' => 'http://test.com/' . Uuid::randomHex(), 'currencyId' => Defaults::CURRENCY, 'languageId' => Defaults::LANGUAGE_SYSTEM, 'snippetSetId' => $snippetSetId],
            ],
        ];

        if ($salesChannelData) {
            $data = array_merge($data, $salesChannelData);
        }

        return $this->createContext($data, []);
    }

    /**
     * @template TEventName of Event
     *
     * @param class-string<TEventName> $eventName
     * @param TEventName|null $eventResult
     */
    protected function catchEvent(string $eventName, ?Event &$eventResult): void
    {
        $this->addEventListener($this->getContainer()->get('event_dispatcher'), $eventName, static function (Event $event) use (&$eventResult): void {
            $eventResult = $event;
        });
    }

    abstract protected static function getContainer(): ContainerInterface;

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'country' => ['id' => $this->getValidCountryId()],
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'foo@bar.de',
            'password' => 'password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = $this->getContainer()->get('customer.repository');

        $repo->create([$customer], Context::createDefaultContext());

        $customer = $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($customer);

        return $customer;
    }

    /**
     * @param array<string, mixed> $salesChannel
     * @param array<string, mixed> $options
     */
    private function createContext(array $salesChannel, array $options): SalesChannelContext
    {
        $factory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $salesChannelId = Uuid::randomHex();
        $salesChannel['id'] = $salesChannelId;
        $salesChannel['customerGroupId'] = TestDefaults::FALLBACK_CUSTOMER_GROUP;

        $salesChannelRepository->create([$salesChannel], Context::createDefaultContext());

        $context = $factory->create(Uuid::randomHex(), $salesChannelId, $options);

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $ruleLoader->loadByToken($context, $context->getToken());

        return $context;
    }
}
