<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Recovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Shopware\Core\System\User\UserException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(UserRecoveryService::class)]
class UserRecoveryServiceTest extends TestCase
{
    private RouterInterface&MockObject $router;

    private EventDispatcherInterface&MockObject $dispatcher;

    private SalesChannelContextService&MockObject $salesChannelContextService;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextService::class);
    }

    public function testGenerateUserRecoveryUserNotFound(): void
    {
        $userEmail = 'nonexistent@example.com';
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $recoveryEntity = new UserRecoveryEntity();
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());

        /** @var StaticEntityRepository<UserCollection> $userRepository */
        $userRepository = new StaticEntityRepository([
            new UserCollection([]),
        ], new UserDefinition());

        /** @var StaticEntityRepository<UserRecoveryCollection> $recoveryRepository */
        $recoveryRepository = new StaticEntityRepository([
            new UserRecoveryCollection([$recoveryEntity]),
            new UserRecoveryCollection([$recoveryEntity]),
        ], new UserRecoveryDefinition());

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([]),
        ], new SalesChannelDefinition());

        $this->dispatcher
            ->expects(static::never())
            ->method('dispatch');

        $service = new UserRecoveryService(
            $recoveryRepository,
            $userRepository,
            $this->router,
            $this->dispatcher,
            $this->salesChannelContextService,
            $salesChannelRepository
        );

        $service->generateUserRecovery($userEmail, $context);
        static::assertCount(0, $recoveryRepository->creates);
        static::assertCount(0, $recoveryRepository->deletes);
    }

    public function testGenerateUserRecoveryWithNoSalesChannel(): void
    {
        static::expectException(UserException::class);
        static::expectExceptionMessage('No sales channel found.');

        $userEmail = 'existing@example.com';
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $user = new UserEntity();
        $user->setUniqueIdentifier(Uuid::randomHex());
        $user->setId(Uuid::randomHex());

        $recoveryEntity = new UserRecoveryEntity();
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());
        $recoveryEntity->setId(Uuid::randomHex());
        $recoveryEntity->setHash(Uuid::randomHex());

        /** @var StaticEntityRepository<UserCollection> $userRepository */
        $userRepository = new StaticEntityRepository([
            new UserCollection([$user]),
        ], new UserDefinition());

        /** @var StaticEntityRepository<UserRecoveryCollection> $recoveryRepository */
        $recoveryRepository = new StaticEntityRepository([
            new UserRecoveryCollection([$recoveryEntity]),
            new UserRecoveryCollection([$recoveryEntity]),
        ], new UserRecoveryDefinition());

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([]),
        ], new SalesChannelDefinition());

        $this->router
            ->expects(static::once())
            ->method('generate')
            ->willReturn('http://example.com');

        $this->dispatcher
            ->expects(static::never())
            ->method('dispatch');

        $service = new UserRecoveryService(
            $recoveryRepository,
            $userRepository,
            $this->router,
            $this->dispatcher,
            $this->salesChannelContextService,
            $salesChannelRepository
        );

        $service->generateUserRecovery($userEmail, $context);
        static::assertCount(0, $recoveryRepository->creates);
        static::assertCount(0, $recoveryRepository->deletes);
    }

    public function testGenerateUserRecoveryWithExistingRecovery(): void
    {
        $userEmail = 'existing@example.com';
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $user = new UserEntity();
        $recoveryEntity = new UserRecoveryEntity();
        $user->setUniqueIdentifier(Uuid::randomHex());
        $user->setId(Uuid::randomHex());
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());
        $recoveryEntity->setId(Uuid::randomHex());
        $recoveryEntity->setHash(Uuid::randomHex());
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setUniqueIdentifier(Uuid::randomHex());
        $salesChannelEntity->setId(Uuid::randomHex());
        $salesChannelEntity->setLanguageId(Uuid::randomHex());
        $salesChannelEntity->setCurrencyId(Uuid::randomHex());

        /** @var StaticEntityRepository<UserCollection> $userRepository */
        $userRepository = new StaticEntityRepository([
            new UserCollection([$user]),
        ], new UserDefinition());

        /** @var StaticEntityRepository<UserRecoveryCollection> $recoveryRepository */
        $recoveryRepository = new StaticEntityRepository([
            new UserRecoveryCollection([$recoveryEntity]),
            new UserRecoveryCollection([$recoveryEntity]),
        ], new UserRecoveryDefinition());

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([$salesChannelEntity]),
        ], new SalesChannelDefinition());

        $this->router
            ->expects(static::once())
            ->method('generate')
            ->willReturn('http://example.com');

        $this->salesChannelContextService
            ->expects(static::once())
            ->method('get')
            ->willReturn($this->createMock(SalesChannelContext::class));

        $this->dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(UserRecoveryRequestEvent::class),
                UserRecoveryRequestEvent::EVENT_NAME
            );

        $service = new UserRecoveryService(
            $recoveryRepository,
            $userRepository,
            $this->router,
            $this->dispatcher,
            $this->salesChannelContextService,
            $salesChannelRepository
        );

        $service->generateUserRecovery($userEmail, $context);
        static::assertCount(1, $recoveryRepository->deletes);
        static::assertCount(1, $recoveryRepository->creates);
    }

    public function testGenerateUserRecoveryWithoutExistingRecovery(): void
    {
        $userEmail = 'existing@example.com';
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $user = new UserEntity();
        $recoveryEntity = new UserRecoveryEntity();
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setUniqueIdentifier(Uuid::randomHex());
        $salesChannelEntity->setId(Uuid::randomHex());
        $salesChannelEntity->setLanguageId(Uuid::randomHex());
        $salesChannelEntity->setCurrencyId(Uuid::randomHex());
        $user->setUniqueIdentifier(Uuid::randomHex());
        $user->setId(Uuid::randomHex());
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());
        $recoveryEntity->setHash(Uuid::randomHex());

        /** @var StaticEntityRepository<UserCollection> $userRepository */
        $userRepository = new StaticEntityRepository([
            new UserCollection([$user]),
        ], new UserDefinition());

        /** @var StaticEntityRepository<UserRecoveryCollection> $recoveryRepository */
        $recoveryRepository = new StaticEntityRepository([
            new UserRecoveryCollection([]),
            new UserRecoveryCollection([$recoveryEntity]),
        ], new UserRecoveryDefinition());

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context) use ($salesChannelEntity) {
                static::assertCount(1, $criteria->getFilters());
                static::assertEquals([
                    new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON)]),
                ], $criteria->getFilters());

                return new SalesChannelCollection([$salesChannelEntity]);
            },
        ], new SalesChannelDefinition());

        $this->router
            ->expects(static::once())
            ->method('generate')
            ->willReturn('http://example.com');

        $this->salesChannelContextService
            ->expects(static::once())
            ->method('get')
            ->willReturn($this->createMock(SalesChannelContext::class));

        $this->dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(UserRecoveryRequestEvent::class),
                UserRecoveryRequestEvent::EVENT_NAME
            );

        $service = new UserRecoveryService(
            $recoveryRepository,
            $userRepository,
            $this->router,
            $this->dispatcher,
            $this->salesChannelContextService,
            $salesChannelRepository
        );

        $service->generateUserRecovery($userEmail, $context);
        static::assertCount(0, $recoveryRepository->deletes);
        static::assertCount(1, $recoveryRepository->creates);
    }
}
