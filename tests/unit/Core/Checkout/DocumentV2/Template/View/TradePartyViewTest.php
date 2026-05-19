<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TradePartyView::class)]
class TradePartyViewTest extends TestCase
{
    public function testBuyerFromOrderComposesFromCustomerAndBillingAddress(): void
    {
        $order = $this->createOrderWithBillingAddress(
            firstName: 'Max',
            lastName: 'Mustermann',
            customerNumber: '1337',
            email: 'max@example.com',
            street: 'Ebbinghoff 10',
            zipcode: '48624',
            city: 'Schöppingen',
            countryIso: 'DE',
        );

        $view = TradePartyView::buyerFromOrder($order);

        static::assertSame('1337', $view->id);
        static::assertSame('Max Mustermann', $view->name);
        static::assertSame('Ebbinghoff 10', $view->street);
        static::assertSame('48624', $view->zipcode);
        static::assertSame('Schöppingen', $view->city);
        static::assertSame('DE', $view->countryIso);
        static::assertSame('max@example.com', $view->email);
    }

    public function testBuyerNameAppendsCompanyWhenPresent(): void
    {
        $order = $this->createOrderWithBillingAddress(
            firstName: 'Jane',
            lastName: 'Doe',
            company: 'Acme GmbH',
            street: '',
            zipcode: '',
            city: '',
            countryIso: 'DE',
        );

        $view = TradePartyView::buyerFromOrder($order);

        static::assertSame('Jane Doe - Acme GmbH', $view->name);
    }

    public function testBuyerCarriesAdditionalAddressLinesAndCountrySubdivision(): void
    {
        $state = new CountryStateEntity();
        $state->setUniqueIdentifier(Uuid::randomHex());
        $state->setName('North Rhine-Westphalia');

        $address = new OrderAddressEntity();
        $address->setUniqueIdentifier(Uuid::randomHex());
        $address->setStreet('Ebbinghoff 10');
        $address->setAdditionalAddressLine1('c/o ACME GmbH');
        $address->setAdditionalAddressLine2('Building 4');
        $address->setZipcode('48624');
        $address->setCity('Schöppingen');
        $address->setCountryState($state);
        $address->setCountry($this->createCountry('DE'));

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setBillingAddress($address);
        $order->setOrderCustomer($this->createCustomer('Max', 'Mustermann'));

        $view = TradePartyView::buyerFromOrder($order);

        static::assertSame('c/o ACME GmbH', $view->additionalAddressLine1);
        static::assertSame('Building 4', $view->additionalAddressLine2);
        static::assertSame('North Rhine-Westphalia', $view->countrySubdivision);
    }

    public function testBuyerFromOrderThrowsWhenNameCannotBeDerived(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($this->createCustomer('', ''));

        static::expectExceptionObject(DocumentV2Exception::invalidOrderData(
            $order->getId(),
            'orderCustomer.name',
            'Buyer name is empty.',
        ));

        TradePartyView::buyerFromOrder($order);
    }

    public function testBuyerFromOrderThrowsWhenBillingAddressIsMissing(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderCustomer($this->createCustomer('Max', 'Mustermann'));

        static::expectExceptionObject(DocumentV2Exception::invalidOrderData(
            $order->getId(),
            'billingAddress',
            'Buyer billing address is missing.',
        ));

        TradePartyView::buyerFromOrder($order);
    }

    public function testBuyerFromOrderThrowsWhenBillingCountryIsMissing(): void
    {
        $address = new OrderAddressEntity();
        $address->setUniqueIdentifier(Uuid::randomHex());
        $address->setStreet('Fallback Street 1');
        $address->setZipcode('11111');
        $address->setCity('Fallback');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setBillingAddress($address);
        $order->setOrderCustomer($this->createCustomer('A', 'B'));

        static::expectExceptionObject(DocumentV2Exception::invalidOrderData(
            $order->getId(),
            'billingAddress.country',
            'Buyer billing address country is missing.',
        ));

        TradePartyView::buyerFromOrder($order);
    }

    public function testBuyerFallsBackToAddressesCollectionWhenBillingAddressNotResolved(): void
    {
        $billingId = Uuid::randomHex();

        $address = new OrderAddressEntity();
        $address->setUniqueIdentifier($billingId);
        $address->setId($billingId);
        $address->setStreet('Fallback Street 1');
        $address->setZipcode('11111');
        $address->setCity('Fallback');
        $address->setCountry($this->createCountry('DE'));

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setBillingAddressId($billingId);
        $order->setOrderCustomer($this->createCustomer('A', 'B'));
        $order->setAddresses(new OrderAddressCollection([$address]));

        $view = TradePartyView::buyerFromOrder($order);

        static::assertSame('Fallback Street 1', $view->street);
        static::assertSame('11111', $view->zipcode);
        static::assertSame('Fallback', $view->city);
    }

    private function createOrderWithBillingAddress(
        string $firstName = '',
        string $lastName = '',
        ?string $company = null,
        string $customerNumber = '',
        string $email = '',
        ?string $street = null,
        ?string $zipcode = null,
        ?string $city = null,
        ?string $countryIso = null,
    ): OrderEntity {
        $address = new OrderAddressEntity();
        $address->setUniqueIdentifier(Uuid::randomHex());

        if ($street !== null) {
            $address->setStreet($street);
        }

        if ($zipcode !== null) {
            $address->setZipcode($zipcode);
        }

        if ($city !== null) {
            $address->setCity($city);
        }

        if ($countryIso !== null) {
            $address->setCountry($this->createCountry($countryIso));
        }

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setBillingAddress($address);
        $order->setOrderCustomer($this->createCustomer($firstName, $lastName, $company, $customerNumber, $email));

        return $order;
    }

    private function createCountry(string $iso): CountryEntity
    {
        $country = new CountryEntity();
        $country->setUniqueIdentifier(Uuid::randomHex());
        $country->setIso($iso);

        return $country;
    }

    private function createCustomer(
        string $firstName,
        string $lastName,
        ?string $company = null,
        string $customerNumber = '',
        string $email = '',
    ): OrderCustomerEntity {
        $customer = new OrderCustomerEntity();
        $customer->setUniqueIdentifier(Uuid::randomHex());
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setCustomerNumber($customerNumber);
        $customer->setEmail($email);

        if ($company !== null) {
            $customer->setCompany($company);
        }

        return $customer;
    }
}
