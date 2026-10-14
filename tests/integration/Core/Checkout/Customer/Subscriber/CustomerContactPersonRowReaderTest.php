<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerContactPersonRowReader;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerContactPersonRowReaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private CustomerContactPersonRowReader $reader;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->reader = static::getContainer()->get(CustomerContactPersonRowReader::class);
    }

    public function testItReadsACustomerWithItsAccountType(): void
    {
        $this->createCustomer();

        $rows = $this->reader->read('customer', [$this->ids->get('customer')], true, false);

        static::assertSame([[
            'id' => $this->ids->get('customer'),
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company' => 'Acme GmbH',
            'account_type' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
        ]], $rows);
    }

    public function testItReadsAnAddressWithoutAnAccountType(): void
    {
        $this->createCustomer();

        $rows = $this->reader->read('customer_address', [$this->ids->get('address')], false, false);

        static::assertSame([[
            'id' => $this->ids->get('address'),
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company' => null,
        ]], $rows);
    }

    public function testItReadsTheOrderSnapshotWithItsVersion(): void
    {
        $this->createOrder();

        $orderCustomerId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `order_customer` WHERE `order_id` = :orderId',
            ['orderId' => Uuid::fromHexToBytes($this->ids->get('order'))]
        );

        static::assertIsString($orderCustomerId);

        static::assertSame([[
            'id' => $orderCustomerId,
            'first_name' => '',
            'last_name' => '',
            'company' => 'Acme GmbH',
            'version_id' => Defaults::LIVE_VERSION,
        ]], $this->reader->read('order_customer', [$orderCustomerId], false, true));

        static::assertSame([[
            'id' => $this->ids->get('order-address'),
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company' => null,
            'version_id' => Defaults::LIVE_VERSION,
        ]], $this->reader->read('order_address', [$this->ids->get('order-address')], false, true));
    }

    public function testItReadsNothingForUnknownIds(): void
    {
        static::assertSame([], $this->reader->read('customer', [Uuid::randomHex()], true, false));
    }

    private function createCustomer(): void
    {
        $customer = (new CustomerBuilder($this->ids, '10001'))
            ->firstName('Ada')
            ->lastName('Lovelace')
            ->add('id', $this->ids->get('customer'))
            ->add('email', Uuid::randomHex() . '@example.com')
            ->add('salesChannelId', TestDefaults::SALES_CHANNEL)
            ->add('password', 'shopware')
            ->add('accountType', CustomerEntity::ACCOUNT_TYPE_BUSINESS)
            ->add('company', 'Acme GmbH')
            ->defaultShippingAddress('address')
            ->defaultBillingAddress('address', [
                'id' => $this->ids->get('address'),
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Ada',
                'lastName' => 'Lovelace',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
            ])
            ->customerGroup(TestDefaults::FALLBACK_CUSTOMER_GROUP);

        static::getContainer()->get('customer.repository')->create([$customer->build()], Context::createDefaultContext());
    }

    private function createOrder(): void
    {
        $this->createCustomer();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->add('id', $this->ids->get('order'))
            ->add('orderDateTime', (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->add('price', new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET))
            ->add('shippingCosts', new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->add('orderCustomer', [
                'customerId' => $this->ids->get('customer'),
                'email' => 'orders@acme.example',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => '',
                'lastName' => '',
                'company' => 'Acme GmbH',
            ])
            ->add('stateId', static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE))
            ->add('paymentMethodId', $this->getValidPaymentMethodId())
            ->add('currencyId', Defaults::CURRENCY)
            ->add('currencyFactor', 1.0)
            ->add('salesChannelId', TestDefaults::SALES_CHANNEL)
            ->addAddress('order-address', [
                'id' => $this->ids->get('order-address'),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Ada',
                'lastName' => 'Lovelace',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
            ])
            ->add('billingAddressId', $this->ids->get('order-address'))
            ->add('shippingAddressId', $this->ids->get('order-address'))
            ->add('context', '{}')
            ->add('payload', '{}')
            ->build();

        static::getContainer()->get('order.repository')->create([$order], Context::createDefaultContext());
    }
}
