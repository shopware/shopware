<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\AddressValidator;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressValidator::class)]
class AddressValidator2Test extends TestCase
{
    #[DataProvider('validationProvider')]
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

        $cart = new Cart('test');
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

    public static function validationProvider(): \Generator
    {
        yield 'test not active' => [false, true, true];
        yield 'test not shipping available' => [true, false, true];
        yield 'test not assigned for sales channel' => [true, true, false];
        yield 'test not active and not shipping available' => [false, false, true];
        yield 'test not active, not shipping available, not assigned' => [false, false, false];
        yield 'test is valid' => [true, true, true];
    }

    private function getSearchResultStub(?bool $assigned = true, ?string $id = null): IdSearchResult
    {
        if ($assigned) {
            return new IdSearchResult(1, [['primaryKey' => $id ?? Uuid::randomHex(), 'data' => []]], new Criteria(), Context::createDefaultContext());
        }

        return new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());
    }

    private function getRepositoryMock(?IdSearchResult $result): EntityRepository&MockObject
    {
        $repository = $this->createMock(EntityRepository::class);

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

    private function getContextMock(?ShippingLocation $shippingLocation = null): MockObject&SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);

        $context->method('getShippingLocation')
            ->willReturn($shippingLocation);
        $context->method('getSalesChannelId')
            ->willReturn(Uuid::randomHex());

        return $context;
    }
}
