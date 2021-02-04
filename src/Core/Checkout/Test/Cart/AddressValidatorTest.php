<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\AddressValidator;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
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
        if ($assigned) {
            $result = new IdSearchResult(0, [['primaryKey' => $id, 'data' => []]], new Criteria(), Context::createDefaultContext());
        } else {
            $result = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());
        }

        // fake database query
        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('searchIds')->willReturn($result);

        $validator = new AddressValidator($repository);

        // fake country entity in context
        $country = new CountryEntity();
        $country->setId($id);
        $country->setActive($active);
        $country->addTranslated('name', 'test');
        $country->setShippingAvailable($shippingAvailable);

        $location = new ShippingLocation($country, null, null);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getShippingLocation')
            ->willReturn($location);

        $context->method('getSalesChannelId')
            ->willReturn(Uuid::randomHex());

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

    public function validationProvider()
    {
        yield 'test not active' => [false, true, true];
        yield 'test not shipping available' => [true, false, true];
        yield 'test not assigned for sales channel' => [true, true, false];
        yield 'test not active and not shipping available' => [false, false, true];
        yield 'test not active, not shipping available, not assigned' => [false, false, false];
        yield 'test is valid' => [true, true, true];
    }
}
