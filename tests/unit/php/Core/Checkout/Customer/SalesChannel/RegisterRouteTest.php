<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @package customer-order
 *
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute
 */
#[Package('customer-order')]
class RegisterRouteTest extends TestCase
{
    public function testAccountType(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->method('get')
            ->willReturn('1');

        $result = $this->createMock(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('first')->willReturn($customerEntity);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects(static::once())
            ->method('create')
            ->willReturnCallback(function (array $create) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('accountType', $create[0]);
                static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $create[0]['accountType']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = new RegisterRoute(
            new EventDispatcher(),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class)
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
        ];

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testCustomFields(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->method('get')
            ->willReturn('1');

        $result = $this->createMock(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('first')->willReturn($customerEntity);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects(static::once())
            ->method('create')
            ->willReturnCallback(function (array $create) {
                static::assertSame(['mapped' => 1], $create[0]['customFields']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $customFieldMapper = $this->createMock(StoreApiCustomFieldMapper::class);
        $customFieldMapper
            ->expects(static::once())
            ->method('map')
            ->with(CustomerDefinition::ENTITY_NAME, new RequestDataBag([
                'test' => 1,
                'mapped' => 1,
            ]))
            ->willReturn(['mapped' => 1]);

        $register = new RegisterRoute(
            new EventDispatcher(),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $customFieldMapper
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'customFields' => [
                'test' => 1,
                'mapped' => 1,
            ],
        ];

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }
}
