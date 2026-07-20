<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
class CartOrderEventSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CustomerAddressCollection>
     */
    private EntityRepository $customerAddressRepository;

    private AbstractSalesChannelContextFactory $contextFactory;

    private SalesChannelContextPersister $contextPersister;

    private CartService $cartService;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->customerAddressRepository = static::getContainer()->get('customer_address.repository');
        $contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->contextFactory = $contextFactory;
        $this->contextPersister = static::getContainer()->get(SalesChannelContextPersister::class);
        $this->cartService = static::getContainer()->get(CartService::class);
    }

    public function testShippingAndBillingAddressResetAfterCheckout(): void
    {
        $customerId = $this->login($this->browser);
        $this->addAdditionalAddress($customerId);

        $productBuilder = new ProductBuilder($this->ids, 'p1');
        $productBuilder->price(100)->visibility($this->ids->get('sales-channel'));
        $productBuilder->write(static::getContainer());
        $productId = $this->ids->get('p1');

        $token = $this->browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        $context = $this->contextFactory->create($token, $this->ids->get('sales-channel'), ['customerId' => $customerId]);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);
        $defaultShippingAddressId = $customer->getDefaultShippingAddressId();
        $defaultBillingAddressId = $customer->getDefaultBillingAddressId();

        $shippingAddress = $context->getShippingLocation()->getAddress();
        static::assertNotNull($shippingAddress, 'Shipping location should have an address');
        $initialShippingId = $shippingAddress->getId();
        static::assertSame(
            $defaultShippingAddressId,
            $initialShippingId,
            \sprintf('Initial shipping address (%s) should be default address (%s)', $initialShippingId, $defaultShippingAddressId)
        );

        $cart = $this->cartService->createNew($token);
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);
        $lineItem->setStackable(true);
        $lineItem->setQuantity(1);
        $cart = $this->cartService->add($cart, $lineItem, $context);

        $additionalAddressId = $this->ids->get('additional-address');
        $this->contextPersister->save(
            $token,
            [
                SalesChannelContextService::SHIPPING_ADDRESS_ID => $additionalAddressId,
                SalesChannelContextService::BILLING_ADDRESS_ID => $additionalAddressId,
            ],
            $this->ids->get('sales-channel'),
            $customerId
        );

        $context = $this->contextFactory->create($token, $this->ids->get('sales-channel'), [
            'customerId' => $customerId,
            SalesChannelContextService::SHIPPING_ADDRESS_ID => $additionalAddressId,
            SalesChannelContextService::BILLING_ADDRESS_ID => $additionalAddressId,
        ]);
        $currentShippingAddress = $context->getShippingLocation()->getAddress();
        static::assertNotNull($currentShippingAddress, 'Shipping location should have an address');
        $currentShippingId = $currentShippingAddress->getId();
        static::assertSame(
            $additionalAddressId,
            $currentShippingId,
            \sprintf('Shipping address should be changed to additional address (%s) but got (%s)', $additionalAddressId, $currentShippingId)
        );

        $orderId = $this->cartService->order($cart, $context, new RequestDataBag());
        static::assertNotEmpty($orderId);

        $freshContext = $this->contextFactory->create($token, $this->ids->get('sales-channel'), ['customerId' => $customerId]);

        $actualShippingAddress = $freshContext->getShippingLocation()->getAddress();
        static::assertNotNull($actualShippingAddress, 'Shipping location should have an address');
        $actualShippingAddressId = $actualShippingAddress->getId();

        $actualBillingAddress = $freshContext->getCustomer()?->getActiveBillingAddress();
        static::assertNotNull($actualBillingAddress, 'Customer should have an active billing address');
        $actualBillingAddressId = $actualBillingAddress->getId();

        $customer = $freshContext->getCustomer();
        static::assertNotNull($customer, 'Customer should be loaded in context');

        static::assertSame(
            $defaultShippingAddressId,
            $actualShippingAddressId,
            \sprintf('Expected shipping address to be reset to customer default (%s) but got %s', $defaultShippingAddressId, $actualShippingAddressId)
        );

        static::assertSame(
            $defaultBillingAddressId,
            $actualBillingAddressId,
            \sprintf('Expected billing address to be reset to customer default (%s) but got %s', $defaultBillingAddressId, $actualBillingAddressId)
        );
    }

    public function testShippingAndBillingAddressResetAfterDeleteCartStoreApi(): void
    {
        $customerId = $this->login($this->browser);
        $this->addAdditionalAddress($customerId);

        $productBuilder = new ProductBuilder($this->ids, 'p1');
        $productBuilder->price(100)->visibility($this->ids->get('sales-channel'));
        $productBuilder->write(static::getContainer());
        $productId = $this->ids->get('p1');

        $token = $this->browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        $context = $this->contextFactory->create($token, $this->ids->get('sales-channel'), ['customerId' => $customerId]);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);
        $defaultShippingAddressId = $customer->getDefaultShippingAddressId();
        $defaultBillingAddressId = $customer->getDefaultBillingAddressId();

        $cart = $this->cartService->createNew($token);
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);
        $lineItem->setStackable(true);
        $lineItem->setQuantity(1);
        $this->cartService->add($cart, $lineItem, $context);

        $additionalAddressId = $this->ids->get('additional-address');
        $this->contextPersister->save(
            $token,
            [
                SalesChannelContextService::SHIPPING_ADDRESS_ID => $additionalAddressId,
                SalesChannelContextService::BILLING_ADDRESS_ID => $additionalAddressId,
            ],
            $this->ids->get('sales-channel'),
            $customerId
        );

        $context = $this->contextFactory->create($token, $this->ids->get('sales-channel'), [
            'customerId' => $customerId,
            SalesChannelContextService::SHIPPING_ADDRESS_ID => $additionalAddressId,
            SalesChannelContextService::BILLING_ADDRESS_ID => $additionalAddressId,
        ]);
        $currentShippingAddress = $context->getShippingLocation()->getAddress();
        static::assertNotNull($currentShippingAddress, 'Shipping location should have an address');
        $currentShippingId = $currentShippingAddress->getId();
        static::assertSame(
            $additionalAddressId,
            $currentShippingId,
            \sprintf('Shipping address should be changed to additional address (%s) but got (%s)', $additionalAddressId, $currentShippingId)
        );

        $this->browser->request('DELETE', '/store-api/checkout/cart');
        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $freshContext = $this->contextFactory->create($token, $this->ids->get('sales-channel'), ['customerId' => $customerId]);

        $actualShippingAddress = $freshContext->getShippingLocation()->getAddress();
        static::assertNotNull($actualShippingAddress, 'Shipping location should have an address');
        $actualShippingAddressId = $actualShippingAddress->getId();

        $actualBillingAddress = $freshContext->getCustomer()?->getActiveBillingAddress();
        static::assertNotNull($actualBillingAddress, 'Customer should have an active billing address');
        $actualBillingAddressId = $actualBillingAddress->getId();

        $customer = $freshContext->getCustomer();
        static::assertNotNull($customer, 'Customer should be loaded in context');

        static::assertSame(
            $defaultShippingAddressId,
            $actualShippingAddressId,
            \sprintf('Expected shipping address to be reset to customer default (%s) but got %s after cart deletion', $defaultShippingAddressId, $actualShippingAddressId)
        );

        static::assertSame(
            $defaultBillingAddressId,
            $actualBillingAddressId,
            \sprintf('Expected billing address to be reset to customer default (%s) but got %s after cart deletion', $defaultBillingAddressId, $actualBillingAddressId)
        );
    }

    private function addAdditionalAddress(string $customerId): void
    {
        $additionalAddressId = $this->ids->get('additional-address');

        $address = [
            'id' => $additionalAddressId,
            'customerId' => $customerId,
            'firstName' => 'Test',
            'lastName' => 'Customer',
            'street' => 'Additional Street 2',
            'city' => 'Additional City',
            'zipcode' => '54321',
            'salutationId' => $this->getValidSalutationId(),
            'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
        ];

        $this->customerAddressRepository->create([$address], Context::createDefaultContext());
    }
}
