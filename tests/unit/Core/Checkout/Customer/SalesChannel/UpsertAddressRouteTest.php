<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
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
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute
 */
#[Package('checkout')]
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
        $result->method('first')->willReturn($address);

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository->method('search')->willReturn($result);
        $addressRepository
            ->expects(static::once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) {
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

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $salutationId = Uuid::randomHex();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->method('upsert')
            ->with(static::callback(function (array $data) use ($salutationId) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame($data[0]['salutationId'], $salutationId);

                return true;
            }));

        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setSalutationId($salutationId);

        $addressRepository->expects(static::once())->method('search')->willReturn(
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
            [['data' => $salutationId, 'primaryKey' => $salutationId]],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $salutationRepository = $this->createMock(EntityRepository::class);
        $salutationRepository->method('searchIds')->willReturn($idSearchResult);

        $systemConfigService = $this->createMock(SystemConfigService::class);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
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
