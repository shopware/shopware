<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigMultipleChangedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;
use Shopware\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Shopware\Core\System\SystemConfig\SymfonySystemConfigService;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(SystemConfigService::class)]
class SystemConfigServiceTest extends TestCase
{
    private Connection&MockObject $connection;

    private ConfigReader&MockObject $configReader;

    private AbstractSystemConfigLoader&MockObject $configLoader;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private SystemConfigService $configService;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->configReader = $this->createMock(ConfigReader::class);
        $this->configLoader = $this->createMock(AbstractSystemConfigLoader::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->configService = new SystemConfigService(
            $this->connection,
            $this->configReader,
            $this->configLoader,
            $this->eventDispatcher,
            new SymfonySystemConfigService([]),
            $this->createMock(CacheTagCollector::class),
            new NativeClock()
        );
    }

    public function testMultipleChangedEventsFired(): void
    {
        $beforeEventAssert = static function (Event|Hookable $event): void {
            static::assertInstanceOf(BeforeSystemConfigMultipleChangedEvent::class, $event);
            $event->setValue('foo.bar', 40);
        };

        $eventAssert = static function (Event|Hookable $event): void {
            static::assertInstanceOf(SystemConfigMultipleChangedEvent::class, $event);
            static::assertSame(40, $event->getConfig()['foo.bar']);
        };

        $expects = $this->exactly(7);
        $this->eventDispatcher
            ->expects($expects)
            ->method('dispatch')
            ->willReturnCallback(static function (Event|Hookable $event) use ($expects, $beforeEventAssert, $eventAssert) {
                match ($expects->numberOfInvocations()) {
                    1 => $beforeEventAssert($event),
                    7 => $eventAssert($event),
                    default => null,
                };

                return $event;
            });

        $this->configService->setMultiple(['foo.bar' => 'value', 'bar.foo' => 50], TestDefaults::SALES_CHANNEL);
    }

    public function testNotAllowedToSetKeysManagedBySystem(): void
    {
        $configService = new SystemConfigService(
            $this->connection,
            $this->configReader,
            $this->configLoader,
            $this->eventDispatcher,
            new SymfonySystemConfigService(['default' => ['core.test' => true]]),
            $this->createMock(CacheTagCollector::class),
            new NativeClock()
        );

        // Setting the same value is okay
        $configService->set('core.test', true);

        $this->expectExceptionObject(SystemConfigException::systemConfigKeyIsManagedBySystems('core.test'));

        $configService->set('core.test', false);
    }

    public function testGetDomainFiltersOutUnrelatedYamlDefaults(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('addOrderBy')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllNumeric')->willReturn([]);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $configService = new SystemConfigService(
            $this->connection,
            $this->configReader,
            $this->configLoader,
            $this->eventDispatcher,
            new SymfonySystemConfigService(['default' => ['foo.bar.key1' => 'value1', 'baz.qux.key2' => 'value2']]),
            $this->createMock(CacheTagCollector::class),
            new NativeClock()
        );

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $configService->getDomain('foo.bar');

        static::assertSame(['foo.bar.key1' => 'value1'], $result);
    }

    public function testGetDomainRejectsEmptyDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain('Empty domain'));

        $this->configService->getDomain('');
    }

    public function testGetDomainRejectsOnlySpacesDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain('Empty domain'));

        $this->configService->getDomain('     ');
    }

    public function testSetRejectsEmptyKey(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidKey('key may not be empty'));

        $this->configService->set('', 'throws error');
    }

    public function testSetRejectsOnlySpacesKey(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidKey('key may not be empty'));

        $this->configService->set('          ', 'throws error');
    }

    public function testSetRejectsInvalidSalesChannelId(): void
    {
        $this->expectException(InvalidUuidException::class);

        $this->configService->set('foo.bar', 'test', 'invalid uuid');
    }

    public function testSetMultiForwardsSilentToHook(): void
    {
        $dispatchedHook = null;
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (Event|Hookable $event) use (&$dispatchedHook) {
                if ($event instanceof SystemConfigChangedHook) {
                    $dispatchedHook = $event;
                }

                return $event;
            });

        $this->configService->setMultiple(['foo.bar' => 'value'], TestDefaults::SALES_CHANNEL, true);

        static::assertInstanceOf(SystemConfigChangedHook::class, $dispatchedHook);
        static::assertTrue($dispatchedHook->silent);
        static::assertSame(TestDefaults::SALES_CHANNEL, $dispatchedHook->salesChannelId);
    }

    #[DisabledFeatures(['v6.8.0.0', 'CACHE_REWORK'])]
    public function testSetMultiDefaultsSilentToFalseWithoutFeatureFlag(): void
    {
        $dispatchedHook = null;
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (Event|Hookable $event) use (&$dispatchedHook) {
                if ($event instanceof SystemConfigChangedHook) {
                    $dispatchedHook = $event;
                }

                return $event;
            });

        $this->configService->setMultiple(['foo.bar' => 'value']);

        static::assertInstanceOf(SystemConfigChangedHook::class, $dispatchedHook);
        static::assertFalse($dispatchedHook->silent);
    }
}
