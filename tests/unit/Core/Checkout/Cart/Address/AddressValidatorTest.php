<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\AddressValidator;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(AddressValidator::class)]
class AddressValidatorTest extends TestCase
{
    private MockObject&EntityRepository $repository;

    private AddressValidator $validator;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->validator = new AddressValidator($this->repository);
    }

    public function testValidateShippingAddressWithMixedItems(): void
    {
        $cart = new Cart('test');
        $cart->add((new LineItem('a', 'test'))->setStates([State::IS_DOWNLOAD]));
        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(false);

        $idSearchResult = new IdSearchResult(
            1,
            [['data' => $country->getId(), 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $shippingLocation = new ShippingLocation($country, null, null);
        $context->method('getShippingLocation')->willReturn($shippingLocation);

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertEquals(0, $errorCollection->count());

        $cart->add((new LineItem('b', 'test'))->setStates([State::IS_PHYSICAL]));

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertEquals(1, $errorCollection->count());
    }

    public function testValidateShippingAddressWithOnlyPhysicalItems(): void
    {
        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);

        $idSearchResult = new IdSearchResult(
            1,
            [['data' => $country->getId(), 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $shippingLocation = new ShippingLocation($country, null, null);
        $context->method('getShippingLocation')->willReturn($shippingLocation);

        $cart->add((new LineItem('b', 'test'))->setStates([State::IS_PHYSICAL]));

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertEquals(0, $errorCollection->count());
    }

    public function testValidateShippingAddressWithOnlyDownloadItems(): void
    {
        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(false);

        $idSearchResult = new IdSearchResult(
            1,
            [['data' => $country->getId(), 'primaryKey' => $country->getId()]],
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->repository->method('searchIds')->willReturn($idSearchResult);

        $shippingLocation = new ShippingLocation($country, null, null);
        $context->method('getShippingLocation')->willReturn($shippingLocation);

        $cart->add((new LineItem('b', 'test'))->setStates([State::IS_DOWNLOAD]));

        $errorCollection = new ErrorCollection();
        $this->validator->validate($cart, $errorCollection, $context);

        static::assertEquals(0, $errorCollection->count());
    }
}
