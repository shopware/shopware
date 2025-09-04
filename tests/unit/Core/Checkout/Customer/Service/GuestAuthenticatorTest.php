<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(GuestAuthenticator::class)]
class GuestAuthenticatorTest extends TestCase
{
    public function testGuestAuthentication(): void
    {
        $order = new OrderEntity();
        $orderCustomer = new OrderCustomerEntity();
        $customer = new CustomerEntity();
        $customer->setGuest(true);
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setEmail('test@example.com');
        $order->setOrderCustomer($orderCustomer);
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode('12345');
        $order->setBillingAddress($billingAddress);
        $request = new Request([
            'email' => 'test@example.com',
            'zipcode' => '12345',
        ]);

        (new GuestAuthenticator())->validate($order, $request);
        $this->expectNotToPerformAssertions();
    }

    public function testGuestAuthenticationWithInvalidCredentials(): void
    {
        $order = new OrderEntity();
        $orderCustomer = new OrderCustomerEntity();
        $customer = new CustomerEntity();
        $customer->setGuest(true);
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setEmail('test@example.com');
        $order->setOrderCustomer($orderCustomer);
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode('12345');
        $order->setBillingAddress($billingAddress);
        $request = new Request([
            'email' => 'foo@bar.com',
            'zipcode' => 'abc',
        ]);

        $this->expectException(CustomerException::class);
        $this->expectExceptionMessage('Wrong credentials for guest authentication.');
        (new GuestAuthenticator())->validate($order, $request);
    }

    public function testGuestAuthenticationWithNoCredentials(): void
    {
        $order = new OrderEntity();
        $orderCustomer = new OrderCustomerEntity();
        $customer = new CustomerEntity();
        $customer->setGuest(true);
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setEmail('test@example.com');
        $order->setOrderCustomer($orderCustomer);
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode('12345');
        $order->setBillingAddress($billingAddress);
        $request = new Request();

        $this->expectException(CustomerException::class);
        $this->expectExceptionMessage('Guest not authenticated.');
        (new GuestAuthenticator())->validate($order, $request);
    }

    public function testGuestAuthenticationWithRegisteredCustomer(): void
    {
        $order = new OrderEntity();
        $orderCustomer = new OrderCustomerEntity();
        $customer = new CustomerEntity();
        $customer->setGuest(false);
        $orderCustomer->setCustomer($customer);
        $order->setOrderCustomer($orderCustomer);
        $request = new Request();

        $this->expectException(CustomerException::class);
        $this->expectExceptionMessage('Customer is not logged in.');
        (new GuestAuthenticator())->validate($order, $request);
    }
}
