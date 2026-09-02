<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Recovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserRecoveryService::class)]
class UserRecoveryServiceTest extends TestCase
{
    use EnvTestBehaviour;

    private const HASH = 'Ynp1oKlXNlLRnjTHVCXBSLnFmQCLLbNe';

    private RouterInterface&MockObject $router;

    private EventDispatcherInterface&MockObject $dispatcher;

    private SalesChannelContextService&MockObject $salesChannelContextService;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextService::class);

        Request::setTrustedHosts([]);
        $this->setEnvVars([
            'APP_URL' => 'https://shop.example.com',
            'SHOPWARE_ADMINISTRATION_PATH_NAME' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
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
            ->expects($this->never())
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

        $this->dispatcher
            ->expects($this->never())
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

        $this->salesChannelContextService
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->createMock(SalesChannelContext::class));

        $this->dispatcher
            ->expects($this->once())
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

        $this->salesChannelContextService
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->createMock(SalesChannelContext::class));

        $this->dispatcher
            ->expects($this->once())
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

    public function testRouterIsNeverCalledWhenNoTrustedHostsAreConfigured(): void
    {
        $this->router->expects($this->never())->method('generate');

        static::assertSame(
            'https://shop.example.com/admin#/login/user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl()
        );
    }

    public function testRecoveryUrlIsGeneratedByRouterWhenTrustedHostsAreConfigured(): void
    {
        Request::setTrustedHosts(['shop.example.com']);

        $this->router->method('generate')->willReturn('https://shop.example.com/admin');

        static::assertSame(
            'https://shop.example.com/admin#/login/user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl()
        );
    }

    public function testRecoveryUrlFallsBackToAppUrlWhenAdministrationRouteIsNotRegistered(): void
    {
        Request::setTrustedHosts(['shop.example.com']);

        $this->router->method('generate')->willThrowException(new RouteNotFoundException());

        static::assertSame(
            'https://shop.example.com/admin#/login/user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl()
        );
    }

    public function testAppUrlIsNotValidatedWhileTheRouterProvidesTheUrl(): void
    {
        Request::setTrustedHosts(['shop.example.com']);
        $this->setEnvVars(['APP_URL' => 'not-a-url']);

        $this->router->method('generate')->willReturn('https://shop.example.com/admin');

        static::assertSame(
            'https://shop.example.com/admin#/login/user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl()
        );
    }

    public function testRecoveryUrlThrowsWhenAppUrlIsInvalidAndAdministrationRouteIsNotRegistered(): void
    {
        Request::setTrustedHosts(['shop.example.com']);
        $this->setEnvVars(['APP_URL' => 'not-a-url']);

        $this->router->method('generate')->willThrowException(new RouteNotFoundException());

        $this->expectExceptionObject(UserException::invalidAppUrl('not-a-url'));

        $this->getGeneratedRecoveryUrl();
    }

    public function testRecoveryUrlUsesConfiguredAdministrationPathName(): void
    {
        $this->setEnvVars([
            'APP_URL' => 'https://shop.example.com/',
            'SHOPWARE_ADMINISTRATION_PATH_NAME' => '/backoffice/',
        ]);

        static::assertSame(
            'https://shop.example.com/backoffice#/login/user-recovery/' . self::HASH,
            $this->getGeneratedRecoveryUrl()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAppUrlProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no scheme' => ['shop.example.com'];
        yield 'unsupported scheme' => ['ftp://shop.example.com'];
        yield 'javascript scheme' => ['javascript://shop.example.com/%0aalert(1)'];
    }

    #[DataProvider('invalidAppUrlProvider')]
    public function testRecoveryUrlThrowsWhenAppUrlIsNotAValidHttpUrl(string $appUrl): void
    {
        $this->setEnvVars(['APP_URL' => $appUrl]);

        $this->expectExceptionObject(UserException::invalidAppUrl(rtrim($appUrl, '/')));

        $this->getGeneratedRecoveryUrl();
    }

    private function getGeneratedRecoveryUrl(): string
    {
        $user = new UserEntity();
        $user->setUniqueIdentifier(Uuid::randomHex());
        $user->setId(Uuid::randomHex());

        $recoveryEntity = new UserRecoveryEntity();
        $recoveryEntity->setUniqueIdentifier(Uuid::randomHex());
        $recoveryEntity->setId(Uuid::randomHex());
        $recoveryEntity->setHash(self::HASH);

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
            new UserRecoveryCollection([]),
            new UserRecoveryCollection([$recoveryEntity]),
        ], new UserRecoveryDefinition());

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([$salesChannelEntity]),
        ], new SalesChannelDefinition());

        $this->salesChannelContextService
            ->method('get')
            ->willReturn($this->createMock(SalesChannelContext::class));

        $recoveryUrl = null;
        $this->dispatcher
            ->method('dispatch')
            ->willReturnCallback(function (UserRecoveryRequestEvent $event) use (&$recoveryUrl) {
                $recoveryUrl = $event->getResetUrl();

                return $event;
            });

        $service = new UserRecoveryService(
            $recoveryRepository,
            $userRepository,
            $this->router,
            $this->dispatcher,
            $this->salesChannelContextService,
            $salesChannelRepository
        );

        $service->generateUserRecovery(
            'existing@example.com',
            new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM])
        );

        static::assertIsString($recoveryUrl);

        return $recoveryUrl;
    }
}
