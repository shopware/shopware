<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\AddressValidator;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressCountryRegionMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressCountryRegionMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressMissingError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressValidator::class)]
class AddressValidatorTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<EntityCollection<Entity>>
     */
    private Stub&EntityRepository $repository;

    private AddressValidator $validator;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->validator = new AddressValidator($this->repository);
    }

    public function testValidateShippingAddressWithMixedItems(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('a', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_DIGITAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_DOWNLOAD]);
        }

        $cart->add($lineItem);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(false);
        $country->setForceStateInRegistration(false);

        $context = Generator::generateSalesChannelContext(customer: $this->createCustomer($country), country: $country);

        $idSearchResult = new IdSearchResult(
            1,
            [$country->getId() => ['data' => [], 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertCount(0, $errorCollection);

        $lineItem = (new LineItem('b', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_PHYSICAL]);
        }

        $cart->add($lineItem);

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertCount(1, $errorCollection);
    }

    public function testValidateShippingAddressWithOnlyPhysicalItems(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('a', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_PHYSICAL]);
        }

        $cart->add($lineItem);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);
        $country->setForceStateInRegistration(false);

        $context = Generator::generateSalesChannelContext(customer: $this->createCustomer($country), country: $country);

        $idSearchResult = new IdSearchResult(
            1,
            [$country->getId() => ['data' => [], 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertCount(0, $errorCollection);
    }

    public function testValidateShippingAddressWithOnlyDownloadItems(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('a', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_DIGITAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_DOWNLOAD]);
        }

        $cart->add($lineItem);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(false);
        $country->setForceStateInRegistration(false);

        $context = Generator::generateSalesChannelContext(customer: $this->createCustomer($country), country: $country);

        $idSearchResult = new IdSearchResult(
            1,
            [$country->getId() => ['data' => [], 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertCount(0, $errorCollection);
    }

    public function testValidateShippingAddressWithoutSalutation(): void
    {
        $cart = new Cart('test');
        $lineItem = (new LineItem('b', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_PHYSICAL]);
        }

        $cart->add($lineItem);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);
        $country->setForceStateInRegistration(true);

        $countryState = new CountryStateEntity();
        $countryState->setId(Uuid::randomHex());
        $countryState->setCountryId($country->getId());
        $countryState->setCountry($country);
        $countryState->setActive(true);

        $countryStates = new CountryStateCollection();
        $countryStates->add($countryState);
        $country->setStates($countryStates);

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());
        $customerAddress->setCountryId($country->getId());
        $customerAddress->setFirstName('John');
        $customerAddress->setLastName('Doe');
        $customerAddress->setCity('ExampleCity');

        $customer = new CustomerEntity();
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setActiveBillingAddress($customerAddress);
        $customer->setActiveShippingAddress($customerAddress);

        $context = Generator::generateSalesChannelContext(customer: $customer, country: $country, countryState: $countryState);

        $idSearchResult = new IdSearchResult(
            1,
            [$country->getId() => ['data' => [], 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertCount(1, $errorCollection);
        static::assertInstanceOf(BillingAddressSalutationMissingError::class, $errorCollection->first());
    }

    public function testValidateAddressWithoutState(): void
    {
        $cart = new Cart('test');
        $lineItem = (new LineItem('b', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_PHYSICAL]);
        }

        $cart->add($lineItem);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);
        $country->setForceStateInRegistration(true);

        $countryState = new CountryStateEntity();
        $countryState->setId(Uuid::randomHex());
        $countryState->setCountryId($country->getId());
        $countryState->setCountry($country);
        $countryState->setActive(true);

        $countryStates = new CountryStateCollection();
        $countryStates->add($countryState);
        $country->setStates($countryStates);

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());
        $customerAddress->setCountryId($country->getId());
        $customerAddress->setFirstName('John');
        $customerAddress->setLastName('Doe');
        $customerAddress->setCity('ExampleCity');
        $customerAddress->setSalutationId(Uuid::randomHex());
        $customerAddress->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setActiveBillingAddress($customerAddress);
        $customer->setActiveShippingAddress($customerAddress);

        $context = Generator::generateSalesChannelContext(customer: $customer, country: $country, countryState: $countryState);

        $idSearchResult = new IdSearchResult(
            1,
            [$country->getId() => ['data' => [], 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertCount(2, $errorCollection);
        static::assertInstanceOf(BillingAddressCountryRegionMissingError::class, $errorCollection->first());
        static::assertInstanceOf(ShippingAddressCountryRegionMissingError::class, $errorCollection->last());
    }

    public function testValidateAddressWithState(): void
    {
        $cart = new Cart('test');

        $lineItem = (new LineItem('b', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_PHYSICAL]);
        }

        $cart->add($lineItem);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);
        $country->setForceStateInRegistration(true);

        $countryState = new CountryStateEntity();
        $countryState->setId(Uuid::randomHex());
        $countryState->setCountryId($country->getId());
        $countryState->setCountry($country);
        $countryState->setActive(true);

        $countryStates = new CountryStateCollection();
        $countryStates->add($countryState);
        $country->setStates($countryStates);

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());
        $customerAddress->setCountryId($country->getId());
        $customerAddress->setFirstName('John');
        $customerAddress->setLastName('Doe');
        $customerAddress->setCity('ExampleCity');
        $customerAddress->setSalutationId(Uuid::randomHex());
        $customerAddress->setCountry($country);
        $customerAddress->setCountryState($countryState);

        $customer = new CustomerEntity();
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setActiveBillingAddress($customerAddress);
        $customer->setActiveShippingAddress($customerAddress);

        $context = Generator::generateSalesChannelContext(customer: $customer, country: $country, countryState: $countryState);

        $idSearchResult = new IdSearchResult(
            1,
            [$country->getId() => ['data' => [], 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertCount(0, $errorCollection);
    }

    public function testValidateAddsBlockingErrorsWhenBothActiveAddressesAreMissing(): void
    {
        $errorCollection = $this->validateWithCustomer(null, null, ProductDefinition::TYPE_PHYSICAL);

        static::assertCount(2, $errorCollection);
        static::assertInstanceOf(BillingAddressMissingError::class, $errorCollection->get('billing-address-missing'));
        static::assertInstanceOf(ShippingAddressMissingError::class, $errorCollection->get('shipping-address-missing'));
        static::assertTrue($errorCollection->blockOrder());
    }

    public function testValidateAddsBlockingErrorWhenOnlyBillingAddressIsMissing(): void
    {
        $country = $this->createCountry(true);
        $errorCollection = $this->validateWithCustomer(null, $this->createAddress($country), ProductDefinition::TYPE_PHYSICAL, $country);

        static::assertCount(1, $errorCollection);
        static::assertInstanceOf(BillingAddressMissingError::class, $errorCollection->get('billing-address-missing'));
        static::assertTrue($errorCollection->blockOrder());
    }

    public function testValidateBlocksDigitalOnlyCartWhenShippingAddressIsMissing(): void
    {
        $country = $this->createCountry(false);
        $errorCollection = $this->validateWithCustomer($this->createAddress($country), null, ProductDefinition::TYPE_DIGITAL, $country);

        static::assertCount(1, $errorCollection);
        static::assertInstanceOf(ShippingAddressMissingError::class, $errorCollection->get('shipping-address-missing'));
        static::assertTrue($errorCollection->blockOrder());
    }

    private function validateWithCustomer(
        ?CustomerAddressEntity $billingAddress,
        ?CustomerAddressEntity $shippingAddress,
        string $productType,
        ?CountryEntity $country = null
    ): ErrorCollection {
        $country ??= $this->createCountry(true);

        $cart = new Cart('test');
        $lineItem = (new LineItem('a', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, $productType);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([$productType === ProductDefinition::TYPE_PHYSICAL ? State::IS_PHYSICAL : State::IS_DOWNLOAD]);
        }

        $cart->add($lineItem);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);

        if ($billingAddress !== null) {
            $customer->setActiveBillingAddress($billingAddress);
        }

        if ($shippingAddress !== null) {
            $customer->setActiveShippingAddress($shippingAddress);
        }

        $this->repository->method('searchIds')->willReturn(new IdSearchResult(
            1,
            [$country->getId() => ['data' => [], 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        ));

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, Generator::generateSalesChannelContext(customer: $customer, country: $country));

        return $errorCollection;
    }

    private function createCountry(bool $shippingAvailable): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable($shippingAvailable);
        $country->setForceStateInRegistration(false);

        return $country;
    }

    private function createAddress(CountryEntity $country): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setCountryId($country->getId());
        $address->setCountry($country);
        $address->setFirstName('John');
        $address->setLastName('Doe');
        $address->setCity('ExampleCity');
        $address->setSalutationId(Uuid::randomHex());

        return $address;
    }

    private function createCustomer(CountryEntity $country): CustomerEntity
    {
        $address = $this->createAddress($country);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setActiveBillingAddress($address);
        $customer->setActiveShippingAddress($address);

        return $customer;
    }
}
