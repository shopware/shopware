<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
class OrderConverterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private OrderConverter $orderConverter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderConverter = static::getContainer()->get(OrderConverter::class);
    }

    public function testConvertToOrderAndSetPrimary(): void
    {
        $cartToken = Uuid::randomHex();
        $cart = $this->getCart($cartToken);
        $context = Generator::generateSalesChannelContext(customer: $this->getCustomer());

        $convertedOrder = $this->orderConverter->convertToOrder($cart, $context, new OrderConversionContext());

        static::assertSame($convertedOrder['deliveries'][0]['id'], $cartToken);
        static::assertNotNull($convertedOrder['transactions'][0]['id']);
    }

    private function getCustomer(): CustomerEntity
    {
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());
        $customerAddress->setFirstName('Test');
        $customerAddress->setLastName('Test');
        $customerAddress->setStreet('Test Street');
        $customerAddress->setCity('Test City');
        $customerAddress->setCountryId($this->getValidCountryId());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setEmail('test@test.com');
        $customer->setFirstName('Test');
        $customer->setLastName('Test');
        $customer->setCustomerNumber(Uuid::randomHex());
        $customer->setActiveBillingAddress($customerAddress);

        return $customer;
    }

    private function getCart(string $id): Cart
    {
        $cart = new Cart('token');

        $cart->setTransactions(
            new TransactionCollection([
                $this->getTransaction(0.5),
                $this->getTransaction(100),
            ])
        );

        $cart->setDeliveries(
            new DeliveryCollection([
                $this->getDelivery(Uuid::randomHex(), 1.0),
                $this->getDelivery($id, 10.5),
            ])
        );

        return $cart;
    }

    private function getTransaction(float $cost): Transaction
    {
        return new Transaction(
            new CalculatedPrice($cost, $cost, new CalculatedTaxCollection(), new TaxRuleCollection()),
            Uuid::randomHex()
        );
    }

    private function getDelivery(string $id, float $cost): Delivery
    {
        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setFirstName('foo');
        $billingAddress->setLastName('bar');
        $billingAddress->setStreet('street');
        $billingAddress->setCity('city');
        $billingAddress->setCountryId(Uuid::randomHex());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable('now'), new \DateTimeImmutable('now')),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, $billingAddress),
            new CalculatedPrice($cost, $cost, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $delivery->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct($id));

        return $delivery;
    }
}
