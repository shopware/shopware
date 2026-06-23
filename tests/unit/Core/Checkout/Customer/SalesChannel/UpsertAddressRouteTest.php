<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(UpsertAddressRoute::class)]
class UpsertAddressRouteTest extends TestCase
{
    public function testCustomFields(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->method('get')
            ->willReturn('1');

        $result = $this->createMock(EntitySearchResult::class);
        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $result->method('getEntities')->willReturn(new CustomerAddressCollection([$address]));

        $salesChannelAddressRepository = $this->createMock(SalesChannelRepository::class);
        $salesChannelAddressRepository->method('search')->willReturn($result);

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) {
                static::assertSame(['mapped' => 1], $data[0]['customFields']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $customFieldMapper = new StoreApiCustomFieldMapper($this->createMock(Connection::class), [
            CustomerAddressDefinition::ENTITY_NAME => [
                ['name' => 'mapped', 'type' => 'int'],
            ],
        ]);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $salesChannelAddressRepository,
            $this->createMock(DataValidator::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customFieldMapper,
            $this->createMock(EntityRepository::class),
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $customer = new CustomerEntity();
        $customer->setId('customer1');

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '1',
            'customFields' => [
                'test' => '1',
                'mapped' => '1',
            ],
        ]);

        $upsert->upsert(null, $data, $salesChannelContext, $customer);
    }

    public function testAddressStringFieldsAreTrimmedBeforeUpsert(): void
    {
        $countryId = Uuid::randomHex();
        $salutationId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) use ($countryId, $salutationId, $customerId) {
                static::assertCount(1, $data);
                static::assertSame($salutationId, $data[0]['salutationId']);
                static::assertSame('Max', $data[0]['firstName']);
                static::assertSame('Mustermann', $data[0]['lastName']);
                static::assertSame('Main Street 1', $data[0]['street']);
                static::assertSame('12345', $data[0]['zipcode']);
                static::assertSame('Berlin', $data[0]['city']);
                static::assertSame('Shopware', $data[0]['company']);
                static::assertSame('Core', $data[0]['department']);
                static::assertSame('Dr.', $data[0]['title']);
                static::assertSame('123456', $data[0]['phoneNumber']);
                static::assertSame('Line 1', $data[0]['additionalAddressLine1']);
                static::assertSame('Line 2', $data[0]['additionalAddressLine2']);
                static::assertSame($countryId, $data[0]['countryId']);
                static::assertNull($data[0]['countryStateId']);
                static::assertSame(['note' => '  keep custom field whitespace  '], $data[0]['customFields']);
                static::assertSame($customerId, $data[0]['customerId']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());

        $salesChannelAddressRepository = $this->createMock(SalesChannelRepository::class);
        $salesChannelAddressRepository->method('search')->willReturn(
            new EntitySearchResult(
                CustomerAddressDefinition::ENTITY_NAME,
                1,
                new CustomerAddressCollection([$address]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $addressValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $addressValidationFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition('address.create'));

        $customFieldMapper = new StoreApiCustomFieldMapper($this->createMock(Connection::class), [
            CustomerAddressDefinition::ENTITY_NAME => [
                ['name' => 'note', 'type' => 'text'],
            ],
        ]);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $salesChannelAddressRepository,
            $this->createMock(DataValidator::class),
            new EventDispatcher(),
            $addressValidationFactory,
            $this->createMock(SystemConfigService::class),
            $customFieldMapper,
            $this->createMock(EntityRepository::class),
        );

        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'salutationId' => $salutationId,
            'firstName' => "\nMax\t",
            'lastName' => "\rMustermann ",
            'street' => "\t Main Street 1 \n",
            'zipcode' => "    12345\t",
            'city' => "\rBerlin\n",
            'countryId' => $countryId,
            'countryStateId' => '',
            'company' => "\tShopware ",
            'department' => "\nCore    ",
            'title' => "\tDr.\n",
            'phoneNumber' => "\t123456\n",
            'additionalAddressLine1' => '        Line 1         ',
            'additionalAddressLine2' => "    Line 2\r",
            'customFields' => [
                'note' => '  keep custom field whitespace  ',
            ],
        ]);

        $upsert->upsert(null, $data, Generator::generateSalesChannelContext(), $customer);
    }

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $salutationId = Uuid::randomHex();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->method('upsert')
            ->with(static::callback(static function (array $data) use ($salutationId) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame($data[0]['salutationId'], $salutationId);

                return true;
            }));

        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setSalutationId($salutationId);

        $salesChannelAddressRepository = $this->createMock(SalesChannelRepository::class);
        $salesChannelAddressRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'customer_address',
                1,
                new EntityCollection([$address]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $idSearchResult = new IdSearchResult(
            1,
            [$salutationId => ['data' => [], 'primaryKey' => $salutationId]],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $salutationRepository = $this->createMock(EntityRepository::class);
        $salutationRepository->method('searchIds')->willReturn($idSearchResult);

        $systemConfigService = $this->createMock(SystemConfigService::class);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $salesChannelAddressRepository,
            $this->createMock(DataValidator::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $this->createMock(StoreApiCustomFieldMapper::class),
            $salutationRepository
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '',
        ]);

        $upsert->upsert(null, $data, $this->createMock(SalesChannelContext::class), $customer);
    }
}
