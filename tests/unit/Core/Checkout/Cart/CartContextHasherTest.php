<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartContextHasher;
use Shopware\Core\Checkout\Cart\CartContextHashStruct;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Event\CartContextHashEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartContextHasher::class)]
class CartContextHasherTest extends TestCase
{
    public const EXPECTED_HASH = '4ec2fde4940aa5dbc3624cc4b04cfd3699ba420d88c931c3710590c6e45d1188';

    private const ADDRESS_ID = '0192b2b8a5f172d0b6b1d3b1b7c1a001';
    private const OTHER_ADDRESS_ID = '0192b2b8a5f172d0b6b1d3b1b7c1a002';
    private const COUNTRY_ID = '0192b2b8a5f172d0b6b1d3b1b7c1a003';
    private const OTHER_COUNTRY_ID = '0192b2b8a5f172d0b6b1d3b1b7c1a004';
    private const COUNTRY_STATE_ID = '0192b2b8a5f172d0b6b1d3b1b7c1a005';
    private const OTHER_COUNTRY_STATE_ID = '0192b2b8a5f172d0b6b1d3b1b7c1a006';

    public EventDispatcher&MockObject $eventDispatcherMock;

    public CartPrice $cartPrice;

    public Cart $cart;

    public SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->cartPrice = new CartPrice(
            11.24,
            14,
            1,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $this->cart = new Cart('token');
        $this->cart->setPrice($this->cartPrice);

        $lineItemChild = new LineItem('line-item-child-id', 'product', 'referenceId2');
        $lineItemChild->setPrice(new CalculatedPrice(
            1,
            24,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        $lineItem1 = new LineItem('line-item-parent-id', 'product', 'referenceId');
        $lineItem1->setPrice(new CalculatedPrice(
            1,
            12,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));
        $lineItem1->addChild($lineItemChild);

        $lineItem2 = new LineItem('line-item-id', 'product', 'referenceId3', 2);
        $lineItem2->setPrice(new CalculatedPrice(
            1,
            14,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        $this->cart->add($lineItem1);
        $this->cart->add($lineItem2);

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('id');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('id');

        $this->context = Generator::generateSalesChannelContext(
            paymentMethod: $paymentMethod,
            shippingMethod: $shippingMethod
        );
    }

    public function testHashIsValid(): void
    {
        $eventDispatcher = new EventDispatcher();

        $cartContextHashService = new CartContextHasher($eventDispatcher);

        $result = $cartContextHashService->isMatching(self::EXPECTED_HASH, $this->cart, $this->context);

        static::assertTrue($result);
    }

    public function testHashIsNotValid(): void
    {
        $eventDispatcher = new EventDispatcher();

        $cartContextHashService = new CartContextHasher($eventDispatcher);

        $result = $cartContextHashService->isMatching('d1942d08767c950d9398bf651fafbb99c580e4e055a9978098be4045b5b93f97', $this->cart, $this->context);

        static::assertFalse($result);
    }

    public function testGetHash(): void
    {
        $eventDispatcher = new EventDispatcher();

        $cartContextHashService = new CartContextHasher($eventDispatcher);

        $result = $cartContextHashService->generate($this->cart, $this->context);

        static::assertSame(self::EXPECTED_HASH, $result);
    }

    public function testCartContextHashChangeEventDispatch(): void
    {
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);

        $hashStruct = new CartContextHashStruct();
        $hashStruct->setPrice(14.0);
        $hashStruct->setPaymentMethod('id');
        $hashStruct->setLineItems([
            'line-item-parent-id' => [
                'quantity' => 1,
                'price' => 12.0,
                'referenceId' => 'referenceId',
                'children' => [
                    'line-item-child-id' => [
                        'quantity' => 1,
                        'price' => 24.0,
                        'referenceId' => 'referenceId2',
                        'children' => [],
                    ],
                ],
            ],
            'line-item-id' => [
                'quantity' => 2,
                'price' => 14.0,
                'referenceId' => 'referenceId3',
                'children' => [],
            ],
        ]);
        $hashStruct->setShippingMethod('id');
        $hashStruct->setBillingAddress(null);
        $hashStruct->setShippingAddress([
            'id' => Generator::CUSTOMER_ADDRESS,
            'countryId' => Generator::COUNTRY,
            'countryStateId' => Generator::COUNTRY_STATE,
            'zipcode' => null,
            'city' => null,
        ]);
        $hashStruct->setCustomer([
            'accountType' => null,
            'company' => null,
            'vatIds' => null,
        ]);

        $this->eventDispatcherMock->expects($this->once())->method('dispatch')
                ->with($event = new CartContextHashEvent($this->context, $this->cart, $hashStruct))
                ->willReturn($event);

        $cartContextHashService = new CartContextHasher($this->eventDispatcherMock);

        $cartContextHashService->generate($this->cart, $this->context);
    }

    /**
     * An admin changing the customer's addresses while the confirm page is open must invalidate the
     * hash.
     *
     * @param \Closure(CustomerAddressEntity, CustomerEntity): void $mutate
     */
    #[DataProvider('taxRelevantChangeProvider')]
    public function testHashChangesWhenTaxRelevantDataChanges(\Closure $mutate): void
    {
        $hasher = new CartContextHasher(new EventDispatcher());

        static::assertNotSame(
            $hasher->generate($this->cart, self::createCheckoutContext()),
            $hasher->generate($this->cart, self::createCheckoutContext($mutate)),
        );
    }

    /**
     * @return iterable<string, array{\Closure(CustomerAddressEntity, CustomerEntity): void}>
     */
    public static function taxRelevantChangeProvider(): iterable
    {
        yield 'a different address is selected' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $address->setId(self::OTHER_ADDRESS_ID);
        }];

        yield 'the country changes' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $country = new CountryEntity();
            $country->setId(self::OTHER_COUNTRY_ID);

            $address->setCountryId(self::OTHER_COUNTRY_ID);
            $address->setCountry($country);
        }];

        yield 'the country state changes' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $countryState = new CountryStateEntity();
            $countryState->setId(self::OTHER_COUNTRY_STATE_ID);

            $address->setCountryStateId(self::OTHER_COUNTRY_STATE_ID);
            $address->setCountryState($countryState);
        }];

        yield 'the zipcode changes' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $address->setZipcode('1000');
        }];

        yield 'the city changes' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $address->setCity('Bruxelles');
        }];

        yield 'the account type changes' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        }];

        yield 'the customer company changes' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $customer->setCompany('Belgium Music');
        }];

        yield 'the vat ids change' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $customer->setVatIds(['BE0123456789']);
        }];
    }

    /**
     * Corrections that change neither the taxation nor the delivery destination must not interrupt a
     * checkout that is already in progress.
     *
     * @param \Closure(CustomerAddressEntity, CustomerEntity): void $mutate
     */
    #[DataProvider('cosmeticChangeProvider')]
    public function testHashIsUnchangedForCosmeticChanges(\Closure $mutate): void
    {
        $hasher = new CartContextHasher(new EventDispatcher());

        static::assertSame(
            $hasher->generate($this->cart, self::createCheckoutContext()),
            $hasher->generate($this->cart, self::createCheckoutContext($mutate)),
        );
    }

    /**
     * @return iterable<string, array{\Closure(CustomerAddressEntity, CustomerEntity): void}>
     */
    public static function cosmeticChangeProvider(): iterable
    {
        yield 'the street is corrected' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $address->setStreet('Weteringstraat 34-2');
        }];

        yield 'the first name is corrected' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $address->setFirstName('Gaga');
        }];

        yield 'the last name is corrected' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $address->setLastName('Komi');
        }];

        yield 'a phone number is added' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $address->setPhoneNumber('+31 20 1234567');
        }];

        yield 'the company on the address is corrected' => [static function (CustomerAddressEntity $address, CustomerEntity $customer): void {
            $address->setCompany('Acme B.V.');
        }];
    }

    public function testHashIsGeneratedWithoutCustomer(): void
    {
        $context = self::createCheckoutContext();
        $context->assign(['customer' => null]);

        $hasher = new CartContextHasher(new EventDispatcher());

        static::assertNotSame(
            $hasher->generate($this->cart, self::createCheckoutContext()),
            $hasher->generate($this->cart, $context),
        );
    }

    /**
     * The generated context carries an address with only its id, country and country state set. The
     * remaining non-nullable properties stay uninitialised, so they must never be read via a getter.
     */
    public function testHashIsGeneratedForPartiallyLoadedAddress(): void
    {
        $hasher = new CartContextHasher(new EventDispatcher());

        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hasher->generate($this->cart, $this->context));
    }

    /**
     * @param \Closure(CustomerAddressEntity, CustomerEntity): void|null $mutate
     */
    private static function createCheckoutContext(?\Closure $mutate = null): SalesChannelContext
    {
        $country = new CountryEntity();
        $country->setId(self::COUNTRY_ID);

        $countryState = new CountryStateEntity();
        $countryState->setId(self::COUNTRY_STATE_ID);
        $countryState->setCountryId(self::COUNTRY_ID);
        $countryState->setCountry($country);

        $address = new CustomerAddressEntity();
        $address->setId(self::ADDRESS_ID);
        $address->setCountryId(self::COUNTRY_ID);
        $address->setCountry($country);
        $address->setCountryStateId(self::COUNTRY_STATE_ID);
        $address->setCountryState($countryState);
        $address->setZipcode('1017');
        $address->setCity('Amsterdam');
        $address->setStreet('Weteringstraat 34-1');
        $address->setFirstName('gaga');
        $address->setLastName('komi');
        $address->setCompany('Acme BV');

        $customer = new CustomerEntity();
        $customer->setId(Generator::CUSTOMER);
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $customer->setCompany('Acme BV');
        $customer->setVatIds(['NL123456789B01']);

        if ($mutate !== null) {
            $mutate($address, $customer);
        }

        $customer->setActiveBillingAddress($address);
        $customer->setActiveShippingAddress($address);

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('id');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('id');

        return Generator::generateSalesChannelContext(
            paymentMethod: $paymentMethod,
            shippingMethod: $shippingMethod,
            shippingLocation: ShippingLocation::createFromAddress($address),
            customer: $customer,
        );
    }
}
