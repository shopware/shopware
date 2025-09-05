<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('provideRequestData')]
    public function testGuestAuthentication(Request $request, ?string $expectedExceptionMessage): void
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

        if ($expectedExceptionMessage !== null) {
            $this->expectException(CustomerException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }
        (new GuestAuthenticator())->validate($order, $request);
        if ($expectedExceptionMessage === null) {
            $this->expectNotToPerformAssertions();
        }
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

    public static function provideRequestData(): array
    {
        return [
            'valid data in query' => [new Request([
                'email' => 'test@example.com',
                'zipcode' => '12345',
            ]), null],
            'valid data in request' => [new Request([], [
                'email' => 'test@example.com',
                'zipcode' => '12345',
            ]), null],
            'invalid email in query' => [new Request([
                'email' => 'foo@bar.com',
                'zipcode' => '12345',
            ]), 'Wrong credentials for guest authentication.'],
            'invalid email in request' => [new Request([], [
                'email' => 'foo@bar.com',
                'zipcode' => '12345',
            ]), 'Wrong credentials for guest authentication.'],
            'invalid zipcode in query' => [new Request([
                'email' => 'test@example.com',
                'zipcode' => 'abc',
            ]), 'Wrong credentials for guest authentication.'],
            'invalid zipcode in request' => [new Request([], [
                'email' => 'test@example.com',
                'zipcode' => 'abc',
            ]), 'Wrong credentials for guest authentication.'],
            'missing zipcode in query' => [new Request([
                'email' => 'test@example.com',
            ]), 'Guest not authenticated.'],
            'missing zipcode in request' => [new Request([], [
                'email' => 'test@example.com',
            ]), 'Guest not authenticated.'],
            'missing email in query' => [new Request([
                'zip' => '12345',
            ]), 'Guest not authenticated.'],
            'missing email in request' => [new Request([], [
                'zip' => '12345',
            ]), 'Guest not authenticated.'],
            'no data' => [new Request(), 'Guest not authenticated.'],
        ];
    }

}
