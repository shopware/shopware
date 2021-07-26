<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\AddressValidator;
use Shopware\Core\Checkout\Cart\Address\Error\SalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AddressValidatorTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;

    /**
     * @dataProvider validationProvider
     */
    public function testValidation(bool $active, bool $shippingAvailable, bool $assigned): void
    {
        $id = Uuid::randomHex();

        // should not assigned to the sales channel?
        $result = $this->getSearchResultStub($assigned, $id);

        // fake database query
        $repository = $this->getRepositoryMock($result);

        $validator = new AddressValidator($repository);

        // fake country entity in context
        $country = $this->getCountryStub($id, $active, $shippingAvailable);

        $location = new ShippingLocation($country, null, null);

        $context = $this->getContextMock($location);

        $cart = new Cart('test', 'test');
        $errors = new ErrorCollection();

        $validator->validate($cart, $errors, $context);

        $shouldBeValid = $assigned && $shippingAvailable && $active;
        if ($shouldBeValid) {
            static::assertCount(0, $errors);

            return;
        }

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ShippingAddressBlockedError::class, $error);
    }

    /**
     * @dataProvider salutationProvider
     * @dataProvider defaultSalutationProvider
     */
    public function testSalutationValidation(
        ?string $salutationId = null,
        ?string $billingAddressSalutationId = null,
        ?string $shippingAddressSalutationId = null
    ): void {
        $id = Uuid::randomHex();

        $result = $this->getSearchResultStub(true, $id);
        $repository = $this->getRepositoryMock($result);
        $validator = new AddressValidator($repository);
        $country = $this->getCountryStub($id);
        $location = new ShippingLocation($country, null, null);
        $context = $this->getContextMock($location);
        $cart = new Cart('test', 'test');
        $errors = new ErrorCollection();

        $context->method('getCustomer')
            ->willReturn($this->getCustomerMock($salutationId, $billingAddressSalutationId, $shippingAddressSalutationId));

        $validator->validate($cart, $errors, $context);

        $allSalutationsSet = array_reduce(
            [$salutationId, $billingAddressSalutationId, $shippingAddressSalutationId],
            static function (bool $carry, ?string $salutationId = null): bool {
                return $carry && $salutationId !== null && $salutationId !== Defaults::SALUTATION;
            },
            true
        );

        if ($allSalutationsSet) {
            static::assertEmpty($errors);
        } else {
            static::assertCount(1, $errors);
            static::assertInstanceOf(SalutationMissingError::class, $errors->first());
        }
    }

    public function validationProvider(): \Generator
    {
        yield 'test not active' => [false, true, true];
        yield 'test not shipping available' => [true, false, true];
        yield 'test not assigned for sales channel' => [true, true, false];
        yield 'test not active and not shipping available' => [false, false, true];
        yield 'test not active, not shipping available, not assigned' => [false, false, false];
        yield 'test is valid' => [true, true, true];
    }

    public function salutationProvider(): \Generator
    {
        yield 'no salutation at all' => [null, null, null];
        yield 'customer salutation' => [Uuid::randomHex(), null, null];
        yield 'billing address salutation' => [null, Uuid::randomHex(), null];
        yield 'customer and billing address salutation' => [Uuid::randomHex(), Uuid::randomHex(), null];
        yield 'shipping address salutation' => [null, null, Uuid::randomHex()];
        yield 'customer and shipping address salutation' => [Uuid::randomHex(), null, Uuid::randomHex()];
        yield 'billing address and shipping address salutation' => [null, Uuid::randomHex(), Uuid::randomHex()];
        yield 'every salutation' => [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
    }

    public function defaultSalutationProvider(): \Generator
    {
        foreach ($this->salutationProvider() as $key => $params) {
            yield $key => array_map(static function (?string $salutationId = null): ?string {
                return $salutationId ? Defaults::SALUTATION : null;
            }, $params);
        }
    }

    private function getSearchResultStub(?bool $assigned = true, ?string $id = null): IdSearchResult
    {
        if ($assigned) {
            return new IdSearchResult(0, [['primaryKey' => $id ?? Uuid::randomHex(), 'data' => []]], new Criteria(), Context::createDefaultContext());
        }

        return new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());
    }

    /**
     * @return EntityRepositoryInterface|MockObject
     */
    private function getRepositoryMock(?IdSearchResult $result)
    {
        $repository = $this->createMock(EntityRepositoryInterface::class);

        $repository->method('searchIds')
            ->willReturn($result);

        return $repository;
    }

    private function getCountryStub(?string $id = null, ?bool $active = true, ?bool $shippingAvailable = true): CountryEntity
    {
        $country = new CountryEntity();

        $country->setId($id ?? Uuid::randomHex());
        $country->setActive((bool) $active);
        $country->addTranslated('name', 'test');
        $country->setShippingAvailable((bool) $shippingAvailable);

        return $country;
    }

    /**
     * @return MockObject|SalesChannelContext
     */
    private function getContextMock(?ShippingLocation $shippingLocation = null)
    {
        $context = $this->createMock(SalesChannelContext::class);

        $context->method('getShippingLocation')
            ->willReturn($shippingLocation);
        $context->method('getSalesChannelId')
            ->willReturn(Uuid::randomHex());

        return $context;
    }

    /**
     * @return MockObject|CustomerAddressEntity
     */
    private function getCustomerAddressMock(?string $salutationId = null)
    {
        $mock = $this->createMock(CustomerAddressEntity::class);

        $mock->method('getSalutationId')
            ->willReturn($salutationId);

        return $mock;
    }

    /**
     * @return MockObject|CustomerEntity
     */
    private function getCustomerMock(
        ?string $salutationId = null,
        ?string $billingAddressSalutationId = null,
        ?string $shippingAddressSalutationId = null
    ) {
        $mock = $this->createMock(CustomerEntity::class);

        $mock->method('getSalutationId')
            ->willReturn($salutationId);
        $mock->method('getActiveBillingAddress')
            ->willReturn($this->getCustomerAddressMock($billingAddressSalutationId));
        $mock->method('getActiveShippingAddress')
            ->willReturn($this->getCustomerAddressMock($shippingAddressSalutationId));

        return $mock;
    }
}
