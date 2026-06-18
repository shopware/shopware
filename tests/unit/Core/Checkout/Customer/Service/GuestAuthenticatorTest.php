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
    public function testGuestAuthentication(Request $request, ?\Exception $expectedException): void
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

        if ($expectedException !== null) {
            $this->expectExceptionObject($expectedException);
        }
        (new GuestAuthenticator())->validate($order, $request);
        if ($expectedException === null) {
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

        $this->expectExceptionObject(CustomerException::customerNotLoggedIn());
        (new GuestAuthenticator())->validate($order, $request);
    }

    #[DataProvider('provideZipcodeWhitespaceData')]
    public function testGuestAuthenticationKeepsZipcodeWhitespaceSignificant(string $storedZipcode, string $requestZipcode, ?\Exception $expectedException): void
    {
        $order = $this->createGuestOrder($storedZipcode);
        $request = new Request([
            'email' => 'test@example.com',
            'zipcode' => $requestZipcode,
        ]);

        if ($expectedException !== null) {
            $this->expectExceptionObject($expectedException);
        }

        (new GuestAuthenticator())->validate($order, $request);

        static::assertNull($expectedException);
    }

    /**
     * @return array<string, array{0: Request, 1: \Exception|null}>
     */
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
            ]), CustomerException::wrongGuestCredentials()],
            'invalid email in request' => [new Request([], [
                'email' => 'foo@bar.com',
                'zipcode' => '12345',
            ]), CustomerException::wrongGuestCredentials()],
            'invalid zipcode in query' => [new Request([
                'email' => 'test@example.com',
                'zipcode' => 'abc',
            ]), CustomerException::wrongGuestCredentials()],
            'invalid zipcode in request' => [new Request([], [
                'email' => 'test@example.com',
                'zipcode' => 'abc',
            ]), CustomerException::wrongGuestCredentials()],
            'missing zipcode in query' => [new Request([
                'email' => 'test@example.com',
            ]), CustomerException::guestNotAuthenticated()],
            'missing zipcode in request' => [new Request([], [
                'email' => 'test@example.com',
            ]), CustomerException::guestNotAuthenticated()],
            'missing email in query' => [new Request([
                'zip' => '12345',
            ]), CustomerException::guestNotAuthenticated()],
            'missing email in request' => [new Request([], [
                'zip' => '12345',
            ]), CustomerException::guestNotAuthenticated()],
            'no data' => [new Request(), CustomerException::guestNotAuthenticated()],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: \Exception|null}>
     */
    public static function provideZipcodeWhitespaceData(): array
    {
        return [
            'matching surrounding spaces' => [' 12345 ', ' 12345 ', null],
            'matching tab and newline' => ["\t12345\n", "\t12345\n", null],
            'trimmed input does not match stored whitespace' => ["\t12345\n", '12345', CustomerException::wrongGuestCredentials()],
            'different surrounding whitespace does not match' => [' 12345 ', "\t12345\n", CustomerException::wrongGuestCredentials()],
        ];
    }

    private function createGuestOrder(string $zipcode): OrderEntity
    {
        $order = new OrderEntity();
        $orderCustomer = new OrderCustomerEntity();
        $customer = new CustomerEntity();
        $customer->setGuest(true);
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setEmail('test@example.com');
        $order->setOrderCustomer($orderCustomer);
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode($zipcode);
        $order->setBillingAddress($billingAddress);

        return $order;
    }
}
