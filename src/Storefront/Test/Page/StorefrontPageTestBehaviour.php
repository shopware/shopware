<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

trait StorefrontPageTestBehaviour
{
    use TaxAddToSalesChannelTestBehaviour;

    public static function assertPageEvent(
        string $expectedClass,
        PageLoadedEvent $event,
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

    abstract protected function getPageLoader();

    protected function assertLoginRequirement(array $queryParams = []): void
    {
        $request = new Request($queryParams);
        $context = $this->createSalesChannelContextWithNavigation();
        $this->expectException(CustomerNotLoggedInException::class);
        $this->getPageLoader()->load($request, $context);
    }

    protected function expectParamMissingException(string $paramName): void
    {
        $this->expectException(MissingRequestParameterException::class);
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

        return $cartService->order($cart, $context);
    }

    protected function getRandomProduct(SalesChannelContext $context): ProductEntity
    {
        $id = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $productRepository = $this->getContainer()->get('product.repository');

        $data = [
            'id' => $id,
            'productNumber' => $productNumber,
            'stock' => 1,
            'name' => StorefrontPageTestConstants::PRODUCT_NAME,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 15],
            'active' => true,
            'categories' => [
                ['id' => Uuid::randomHex(), 'name' => 'asd'],
            ],
            'visibilities' => [
                ['salesChannelId' => $context->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $productRepository->create([$data], $context->getContext());
        $this->addTaxDataToSalesChannel($context, $data['tax']);

        /** @var SalesChannelRepositoryInterface $storefrontProductRepository */
        $storefrontProductRepository = $this->getContainer()->get('sales_channel.product.repository');
        $searchResult = $storefrontProductRepository->search(new Criteria([$id]), $context);

        return $searchResult->first();
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

    protected function createSalesChannelContext(): SalesChannelContext
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

    protected function catchEvent(string $eventName, &$eventResult): void
    {
        $this->getContainer()->get('event_dispatcher')->addListener($eventName, static function ($event) use (&$eventResult): void {
            $eventResult = $event;
        });
    }

    abstract protected function getContainer(): ContainerInterface;

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'foo@bar.de',
                'password' => 'password',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

    private function createContext(array $salesChannel, array $options): SalesChannelContext
    {
        $factory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $salesChannelId = Uuid::randomHex();
        $salesChannel['id'] = $salesChannelId;
        $salesChannel['customerGroupId'] = Defaults::FALLBACK_CUSTOMER_GROUP;

        $salesChannelRepository->create([$salesChannel], Context::createDefaultContext());

        $context = $factory->create(Uuid::randomHex(), $salesChannelId, $options);

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);
        $ruleLoader->loadByToken($context, $context->getToken());

        return $context;
    }
}
