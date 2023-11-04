<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\DeleteUnusedGuestCustomerService;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class DeleteUnusedGuestCustomerServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private DeleteUnusedGuestCustomerService $service;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->service = $this->getContainer()->get(DeleteUnusedGuestCustomerService::class);

        $this->getContainer()
            ->get(SystemConfigService::class)
            ->set('core.loginRegistration.unusedGuestCustomerLifetime', 86400);
    }

    public function testItDeletesUnusedGuestCustomer(): void
    {
        $context = Context::createDefaultContext();
        $customerRepository = $this->getContainer()->get('customer.repository');

        $customer = (new CustomerBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerRepository->create([$customer->build()], $context);

        static::assertEquals(1, $this->service->countUnusedCustomers($context));

        $this->service->deleteUnusedCustomers($context);

        static::assertEquals(0, $this->service->countUnusedCustomers($context));

        $result = $customerRepository->search(new Criteria([$this->ids->get('10000')]), $context);

        static::assertEquals(0, $result->getTotal());
    }

    public function testItDoesOnlyDeleteGuestCustomers(): void
    {
        $context = Context::createDefaultContext();
        $customerRepository = $this->getContainer()->get('customer.repository');

        $customer = (new CustomerBuilder($this->ids, '10000'))
            ->add('guest', false)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $guestCustomer = (new CustomerBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerRepository->create([
            $customer->build(),
            $guestCustomer->build(),
        ], $context);

        static::assertEquals(1, $this->service->countUnusedCustomers($context));

        $this->service->deleteUnusedCustomers($context);

        static::assertEquals(0, $this->service->countUnusedCustomers($context));

        $result = $customerRepository->search(new Criteria([
            $this->ids->get('10000'),
            $this->ids->get('10001'),
        ]), $context);

        static::assertEquals(1, $result->getTotal());

        /** @var CustomerEntity $entity */
        $entity = $result->first();

        // expect the non-guest customer to still exist
        static::assertEquals('10000', $entity->getCustomerNumber());
    }

    public function testItDeletesOnlyExpiredCustomerAccounts(): void
    {
        $context = Context::createDefaultContext();
        $customerRepository = $this->getContainer()->get('customer.repository');

        $nonExpiredCustomer = (new CustomerBuilder($this->ids, '10000'))
            ->add('guest', true)
            // guest account is not expired as max lifetime is 86400s = 24 hours
            ->add('createdAt', new \DateTime('- 2 hours'));

        $expiredCustomer = (new CustomerBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerRepository->create([
            $nonExpiredCustomer->build(),
            $expiredCustomer->build(),
        ], $context);

        static::assertEquals(1, $this->service->countUnusedCustomers($context));

        $this->service->deleteUnusedCustomers($context);

        static::assertEquals(0, $this->service->countUnusedCustomers($context));

        $result = $customerRepository->search(new Criteria([
            $this->ids->get('10000'),
            $this->ids->get('10001'),
        ]), $context);

        static::assertEquals(1, $result->getTotal());

        /** @var CustomerEntity $entity */
        $entity = $result->first();

        // expect the non expired customer to still exist
        static::assertEquals('10000', $entity->getCustomerNumber());
    }

    public function testItDoesOnlyDeleteCustomersWithoutOrders(): void
    {
        $context = Context::createDefaultContext();
        $customerRepository = $this->getContainer()->get('customer.repository');

        $customerWithOrder = (new CustomerBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerWithoutOrder = (new CustomerBuilder($this->ids, '10001'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerRepository->create([
            $customerWithOrder->build(),
            $customerWithoutOrder->build(),
        ], $context);

        $this->createOrderForCustomer($customerWithOrder->build());

        static::assertEquals(1, $this->service->countUnusedCustomers($context));

        $this->service->deleteUnusedCustomers($context);

        static::assertEquals(0, $this->service->countUnusedCustomers($context));

        $result = $customerRepository->search(new Criteria([
            $this->ids->get('10000'),
            $this->ids->get('10001'),
        ]), $context);

        static::assertEquals(1, $result->getTotal());

        /** @var CustomerEntity $entity */
        $entity = $result->first();

        // expect the customer with an order to still exist
        static::assertEquals('10000', $entity->getCustomerNumber());
    }

    public function testItCancelsWhenMaxLifeTimeIsZero(): void
    {
        $this->getContainer()
            ->get(SystemConfigService::class)
            ->set('core.loginRegistration.unusedGuestCustomerLifetime', 0);

        $context = Context::createDefaultContext();
        $customerRepository = $this->getContainer()->get('customer.repository');

        $customer = (new CustomerBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerRepository->create([$customer->build()], $context);

        static::assertEquals(0, $this->service->countUnusedCustomers($context));

        $this->service->deleteUnusedCustomers($context);

        static::assertEquals(0, $this->service->countUnusedCustomers($context));

        $result = $customerRepository->search(new Criteria([$this->ids->get('10000')]), $context);

        static::assertEquals(1, $result->getTotal());

        /** @var CustomerEntity $entity */
        $entity = $result->first();

        // expect the customer to still exist
        static::assertEquals('10000', $entity->getCustomerNumber());
    }

    public function testItCancelsWhenMaxLifeTimeIsNull(): void
    {
        $this->getContainer()
            ->get(SystemConfigService::class)
            ->set('core.loginRegistration.unusedGuestCustomerLifetime', null);

        $context = Context::createDefaultContext();
        $customerRepository = $this->getContainer()->get('customer.repository');

        $customer = (new CustomerBuilder($this->ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'));

        $customerRepository->create([$customer->build()], $context);

        static::assertEquals(0, $this->service->countUnusedCustomers($context));

        $this->service->deleteUnusedCustomers($context);

        static::assertEquals(0, $this->service->countUnusedCustomers($context));

        $result = $customerRepository->search(new Criteria([$this->ids->get('10000')]), $context);

        static::assertEquals(1, $result->getTotal());

        /** @var CustomerEntity $entity */
        $entity = $result->first();

        // expect the customer to still exist
        static::assertEquals('10000', $entity->getCustomerNumber());
    }

    private function createOrderForCustomer(array $customer): string
    {
        $stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $productRepository = $this->getContainer()->get('product.repository');
        $orderRepository = $this->getContainer()->get('order.repository');

        $product = (new ProductBuilder($this->ids, 'Product-1'))
            ->price(10)
            ->build();

        $productRepository->create([$product], Context::createDefaultContext());

        $orderId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'deliveries' => [
                [
                    'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                    'shippingMethodId' => $this->getValidShippingMethodId(),
                    'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingDateEarliest' => date(\DATE_ISO8601),
                    'shippingDateLatest' => date(\DATE_ISO8601),
                    'shippingOrderAddressId' => $customer['defaultShippingAddressId'],
                    'positions' => [
                        [
                            'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            'orderLineItemId' => $this->ids->get('Product-1'),
                        ],
                    ],
                ],
            ],
            'lineItems' => [
                [
                    'id' => $this->ids->get('Product-1'),
                    'identifier' => 'test',
                    'quantity' => 1,
                    'type' => 'test',
                    'label' => 'test',
                    'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                    'good' => true,
                ],
            ],
            'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
            'orderCustomer' => [
                'email' => $customer['email'],
                'firstName' => $customer['firstName'],
                'lastName' => $customer['lastName'],
                'customerNumber' => $customer['customerNumber'],
                'salutationId' => $customer['salutationId'] ?? $this->getValidSalutationId(),
                'customerId' => $customer['id'],
            ],
            'billingAddressId' => $customer['defaultBillingAddressId'],
        ];

        $orderRepository->create([$order], Context::createDefaultContext());

        return $orderId;
    }
}
