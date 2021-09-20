<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\IpUtils;

class RemoteAddressFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRemoteAddressSerializerInvalidField(): void
    {
        $serializer = $this->getSerializer();
        $data = new KeyValuePair('remoteAddress', null, false);

        $this->expectException(InvalidSerializerFieldException::class);
        $serializer->encode(
            (new IntField('remote_address', 'remoteAddress'))->addFlags(new ApiAware()),
            $this->getEntityExisting(),
            $data,
            $this->getWriteParameterBagMock()
        )->current();
    }

    public function testRemoteAddressSerializerValidField(): void
    {
        $serializer = $this->getSerializer();
        $data = new KeyValuePair('remoteAddress', '127.0.0.1', false);

        try {
            $serializer->encode(
                $this->getRemoteAddressField(),
                $this->getEntityExisting(),
                $data,
                $this->getWriteParameterBagMock()
            )->current();

            static::assertTrue(true);
        } catch (InvalidSerializerFieldException $e) {
            static::fail();
        }
    }

    public function testRemoteAddressSerializerAnonymize(): void
    {
        $this->setConfig();

        $remoteAddress = '127.0.0.1';
        $orderId = $this->createOrderWithRemoteAddress($remoteAddress);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $result = $this->getContainer()->get('order_customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertNotEmpty($result);
        static::assertNotSame($remoteAddress, $result->getRemoteAddress());
        static::assertSame(IPUtils::anonymize($remoteAddress), $result->getRemoteAddress());
    }

    public function testRemoteAddressSerializerNoAnonymize(): void
    {
        $this->setConfig(true);

        $remoteAddress = '127.0.0.1';
        $orderId = $this->createOrderWithRemoteAddress($remoteAddress);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $result = $this->getContainer()->get('order_customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertNotEmpty($result);
        static::assertSame($remoteAddress, $result->getRemoteAddress());
    }

    public function testSetRemoteAddressByLogin(): void
    {
        $this->setConfig();

        $customerId = $this->createCustomer();

        $this->getContainer()->get(AccountService::class)
            ->login('test@example.com', $this->createSalesChannelContext(), true);

        $criteria = new Criteria([$customerId]);
        $customer = $this->getContainer()->get('customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertNotEmpty($customer);
        static::assertNotSame('127.0.0.1', $customer->getRemoteAddress());
        static::assertSame(IPUtils::anonymize('127.0.0.1'), $customer->getRemoteAddress());
    }

    private function setConfig(bool $value = false): void
    {
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.customerIpAddressesNotAnonymously', $value);
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    private function createOrderWithRemoteAddress(string $remoteAddress): string
    {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $stateId = $this->getContainer()->get(StateMachineRegistry::class)
            ->getInitialState(OrderStates::STATE_MACHINE, Context::createDefaultContext())->getId();

        $customerId = $this->createCustomer();

        $order = [
            'id' => $orderId,
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'remoteAddress' => $remoteAddress,
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->getContainer()->get('order.repository')->upsert([$order], Context::createDefaultContext());

        return $orderId;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'remoteAddress' => '127.0.0.1',
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()->get('customer.repository')->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function getSerializer(): RemoteAddressFieldSerializer
    {
        return $this->getContainer()->get(RemoteAddressFieldSerializer::class);
    }

    private function getWriteParameterBagMock(): WriteParameterBag
    {
        $mockBuilder = $this->getMockBuilder(WriteParameterBag::class);
        $mockBuilder->disableOriginalConstructor();

        return $mockBuilder->getMock();
    }

    private function getEntityExisting(): EntityExistence
    {
        return new EntityExistence(null, [], true, false, false, []);
    }

    private function getRemoteAddressField(): RemoteAddressField
    {
        return new RemoteAddressField('remote_address', 'remoteAddress');
    }
}
