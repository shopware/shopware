<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigMultipleChangedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;
use Shopware\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Shopware\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Shopware\Core\System\SystemConfig\SymfonySystemConfigService;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\System\SystemConfig\SystemConfigLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class SystemConfigServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemConfigService = new SystemConfigService(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(ConfigReader::class),
            static::getContainer()->get(SystemConfigLoader::class),
            static::getContainer()->get('event_dispatcher'),
            new SymfonySystemConfigService([]),
            static::getContainer()->get(CacheTagCollector::class),
            new NativeClock()
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function differentTypesProvider(): iterable
    {
        yield 'boolean true value is stored unchanged' => [true];
        yield 'boolean false value is stored unchanged' => [false];
        yield 'null value is stored unchanged' => [null];
        yield 'zero integer value is stored unchanged' => [0];
        yield 'positive integer value is stored unchanged' => [1234];
        yield 'float value is stored unchanged' => [1243.42314];
        yield 'empty string value is stored unchanged' => [''];
        yield 'string value is stored unchanged' => ['test'];
        yield 'array value is stored unchanged' => [['foo' => 'bar']];
    }

    /**
     * @param float|bool|int|string|array<mixed>|null $expected
     */
    #[DataProvider('differentTypesProvider')]
    public function testSetGetDifferentTypes(array|float|bool|int|string|null $expected): void
    {
        $this->systemConfigService->set('foo.bar', $expected);
        $actual = $this->systemConfigService->get('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function getStringProvider(): iterable
    {
        yield 'true value is read as string one' => [true, '1'];
        yield 'false value is read as empty string' => [false, ''];
        yield 'null value is read as empty string' => [null, ''];
        yield 'zero integer is read as string zero' => [0, '0'];
        yield 'positive integer is read as string integer' => [1234, '1234'];
        yield 'float value is read as string float' => [1243.42314, '1243.42314'];
        yield 'empty string is read unchanged' => ['', ''];
        yield 'string value is read unchanged' => ['test', 'test'];
        yield 'array value is invalid for string reads' => [['foo' => 'bar'], ''];
    }

    /**
     * @param array<mixed>|bool|int|float|string|null $writtenValue
     */
    #[DataProvider('getStringProvider')]
    public function testGetString($writtenValue, string $expected): void
    {
        $this->systemConfigService->set('foo.bar', $writtenValue);
        if (\is_array($writtenValue)) {
            $this->expectExceptionObject(SystemConfigException::invalidSettingValueException('foo.bar', 'string', 'array'));
        }
        $actual = $this->systemConfigService->getString('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function getIntProvider(): iterable
    {
        yield 'true value is read as integer one' => [true, 1];
        yield 'false value is read as integer zero' => [false, 0];
        yield 'null value is read as integer zero' => [null, 0];
        yield 'zero integer is read unchanged' => [0, 0];
        yield 'positive integer is read unchanged' => [1234, 1234];
        yield 'float value is truncated to integer' => [1243.42314, 1243];
        yield 'empty string is read as integer zero' => ['', 0];
        yield 'non numeric string is read as integer zero' => ['test', 0];
        yield 'array value is invalid for integer reads' => [['foo' => 'bar'], 0];
    }

    /**
     * @param float|bool|int|string|array<mixed>|null $writtenValue
     */
    #[DataProvider('getIntProvider')]
    public function testGetInt(array|float|bool|int|string|null $writtenValue, int $expected): void
    {
        $this->systemConfigService->set('foo.bar', $writtenValue);
        if (\is_array($writtenValue)) {
            $this->expectExceptionObject(SystemConfigException::invalidSettingValueException('foo.bar', 'int', 'array'));
        }
        $actual = $this->systemConfigService->getInt('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{mixed, float}>
     */
    public static function getFloatProvider(): iterable
    {
        yield 'true value is read as float one' => [true, 1];
        yield 'false value is read as float zero' => [false, 0];
        yield 'null value is read as float zero' => [null, 0];
        yield 'zero integer is read as float zero' => [0, 0];
        yield 'positive integer is read as float value' => [1234, 1234];
        yield 'float value is read unchanged' => [1243.42314, 1243.42314];
        yield 'empty string is read as float zero' => ['', 0];
        yield 'non numeric string is read as float zero' => ['test', 0];
        yield 'array value is invalid for float reads' => [['foo' => 'bar'], 0];
    }

    /**
     * @param float|bool|int|string|array<mixed>|null $writtenValue
     */
    #[DataProvider('getFloatProvider')]
    public function testGetFloat(array|float|bool|int|string|null $writtenValue, float $expected): void
    {
        $this->systemConfigService->set('foo.bar', $writtenValue);
        if (\is_array($writtenValue)) {
            $this->expectExceptionObject(SystemConfigException::invalidSettingValueException('foo.bar', 'float', 'array'));
        }
        $actual = $this->systemConfigService->getFloat('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function getBoolProvider(): iterable
    {
        yield 'true value is read as true' => [true, true];
        yield 'false value is read as false' => [false, false];
        yield 'null value is read as false' => [null, false];
        yield 'zero integer is read as false' => [0, false];
        yield 'positive integer is read as true' => [1234, true];
        yield 'float value is read as true' => [1243.42314, true];
        yield 'empty string is read as false' => ['', false];
        yield 'non empty string is read as true' => ['test', true];
        yield 'non empty array value is read as true' => [['foo' => 'bar'], true];
        yield 'array value is read as false' => [[], false];
    }

    /**
     * @param float|bool|int|string|array<mixed>|null $writtenValue
     */
    #[DataProvider('getBoolProvider')]
    public function testGetBool(array|float|bool|int|string|null $writtenValue, bool $expected): void
    {
        $this->systemConfigService->set('foo.bar', $writtenValue);
        $actual = $this->systemConfigService->getBool('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * mysql 5.7.30 casts 0.0 to 0
     */
    public function testFloatZero(): void
    {
        $this->systemConfigService->set('foo.bar', 0.0);
        $actual = $this->systemConfigService->get('foo.bar');
        static::assertSame(0.0, $actual);
    }

    public function testSetGetSalesChannel(): void
    {
        $this->systemConfigService->set('foo.bar', 'test');
        $actual = $this->systemConfigService->get('foo.bar', TestDefaults::SALES_CHANNEL);
        static::assertSame('test', $actual);

        $this->systemConfigService->set('foo.bar', 'override', TestDefaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('foo.bar', TestDefaults::SALES_CHANNEL);
        static::assertSame('override', $actual);

        $this->systemConfigService->set('foo.bar', '', TestDefaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('foo.bar', TestDefaults::SALES_CHANNEL);
        static::assertSame('', $actual);
    }

    public function testSetGetSalesChannelBool(): void
    {
        $this->systemConfigService->set('foo.bar', false);
        $actual = $this->systemConfigService->get('foo.bar', TestDefaults::SALES_CHANNEL);
        static::assertFalse($actual);

        $this->systemConfigService->set('foo.bar', true, TestDefaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('foo.bar', TestDefaults::SALES_CHANNEL);
        static::assertTrue($actual);
    }

    public function testGetDomainNoData(): void
    {
        $actual = $this->systemConfigService->getDomain('foo');
        static::assertSame([], $actual);

        $actual = $this->systemConfigService->getDomain('foo', null, true);
        static::assertSame([], $actual);

        $actual = $this->systemConfigService->getDomain('foo', TestDefaults::SALES_CHANNEL);
        static::assertSame([], $actual);

        $actual = $this->systemConfigService->getDomain('foo', TestDefaults::SALES_CHANNEL, true);
        static::assertSame([], $actual);
    }

    public function testGetDomain(): void
    {
        $this->systemConfigService->set('foo.a', 'a');
        $this->systemConfigService->set('foo.b', 'b');
        $this->systemConfigService->set('foo.c', 'c');
        $this->systemConfigService->set('foo.c', 'c override', TestDefaults::SALES_CHANNEL);

        $expected = [
            'foo.a' => 'a',
            'foo.b' => 'b',
            'foo.c' => 'c',
        ];
        $actual = $this->systemConfigService->getDomain('foo');
        static::assertSame($expected, $actual);

        $expected = [
            'foo.a' => 'a',
            'foo.b' => 'b',
            'foo.c' => 'c override',
        ];
        $actual = $this->systemConfigService->getDomain('foo', TestDefaults::SALES_CHANNEL, true);
        static::assertSame($expected, $actual);

        $expected = [
            'foo.c' => 'c override',
        ];
        $actual = $this->systemConfigService->getDomain('foo', TestDefaults::SALES_CHANNEL);
        static::assertSame($expected, $actual);
    }

    public function testGetDomainInherit(): void
    {
        $this->systemConfigService->set('foo.bar', 'test');
        $this->systemConfigService->set('foo.bar', 'override', TestDefaults::SALES_CHANNEL);
        $this->systemConfigService->set('foo.bar', '', TestDefaults::SALES_CHANNEL);

        $expected = ['foo.bar' => 'test'];
        $actual = $this->systemConfigService->getDomain('foo', TestDefaults::SALES_CHANNEL, true);

        static::assertSame($expected, $actual);
    }

    public function testGetDomainInheritWithBooleanValue(): void
    {
        $this->systemConfigService->set('foo.bar', true);
        $actual = $this->systemConfigService->getDomain('foo', TestDefaults::SALES_CHANNEL, true);

        // assert that the service reads the default value, when no sales-channel-specific value is configured
        static::assertSame(['foo.bar' => true], $actual);

        $this->systemConfigService->set('foo.bar', false, TestDefaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->getDomain('foo', TestDefaults::SALES_CHANNEL, true);

        // assert that the service reads the sales-channel-specific value when one is configured
        static::assertSame(['foo.bar' => false], $actual);
    }

    public function testGetDomainWithDots(): void
    {
        $this->systemConfigService->set('foo.a', 'a');
        $actual = $this->systemConfigService->getDomain('foo.');
        static::assertSame(['foo.a' => 'a'], $actual);
    }

    public function testDeleteNonExisting(): void
    {
        $this->systemConfigService->delete('not.found');
        $actual = $this->systemConfigService->get('not.found');
        static::assertNull($actual);

        $this->systemConfigService->delete('not.found', TestDefaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('not.found', TestDefaults::SALES_CHANNEL);
        static::assertNull($actual);
    }

    public function testDelete(): void
    {
        $this->systemConfigService->set('foo', 'bar');
        $this->systemConfigService->set('foo', 'bar override', TestDefaults::SALES_CHANNEL);

        $this->systemConfigService->delete('foo');
        $actual = $this->systemConfigService->get('foo');
        static::assertNull($actual);
        $actual = $this->systemConfigService->get('foo', TestDefaults::SALES_CHANNEL);
        static::assertSame('bar override', $actual);

        $this->systemConfigService->delete('foo', TestDefaults::SALES_CHANNEL);
        $actual = $this->systemConfigService->get('foo', TestDefaults::SALES_CHANNEL);
        static::assertNull($actual);
    }

    public function testWebhookEventsFired(): void
    {
        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $called = false;

        $this->addEventListener($eventDispatcher, SystemConfigChangedHook::class, static function (SystemConfigChangedHook $event) use (&$called): void {
            static::assertSame([
                'changes' => ['foo.bar'],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ], $event->getWebhookPayload());

            $called = true;
        });

        $this->systemConfigService->set('foo.bar', 'test', TestDefaults::SALES_CHANNEL);

        static::assertTrue($called);
    }

    public function testDeleteExtensionConfigurationDeletesAcrossAllSalesChannels(): void
    {
        $extensionName = 'SwagTest';
        $configKey1 = $extensionName . '.config.testSetting1';
        $configKey2 = $extensionName . '.config.testSetting2';

        // Create three records, 2 global and 1 sales channel specific
        $this->systemConfigService->set($configKey1, 'global_value');
        $this->systemConfigService->set($configKey1, 'sales_channel_value', TestDefaults::SALES_CHANNEL);
        $this->systemConfigService->set($configKey2, true);

        // Verify that the records exist
        static::assertSame('global_value', $this->systemConfigService->get($configKey1));
        static::assertSame('sales_channel_value', $this->systemConfigService->get($configKey1, TestDefaults::SALES_CHANNEL));
        static::assertTrue($this->systemConfigService->getBool($configKey2));
        static::assertTrue($this->systemConfigService->getBool($configKey2, TestDefaults::SALES_CHANNEL));

        // Add event listeners to capture dispatched events, structured by scope
        $dispatchedEvents = [];
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = static function (
            BeforeSystemConfigMultipleChangedEvent|SystemConfigMultipleChangedEvent|SystemConfigChangedHook $event
        ) use (&$dispatchedEvents): void {
            $eventClass = $event::class;

            if ($event instanceof SystemConfigChangedHook) {
                $payload = $event->getWebhookPayload();
                static::assertArrayHasKey('salesChannelId', $payload);
                $salesChannelId = $payload['salesChannelId'];
            } else {
                $salesChannelId = $event->getSalesChannelId();
            }

            $scope = $salesChannelId === null ? 'global' : 'sales_channel';
            $dispatchedEvents[$eventClass][$scope][] = $event;
        };

        $this->addEventListener($eventDispatcher, BeforeSystemConfigMultipleChangedEvent::class, $listener);
        $this->addEventListener($eventDispatcher, SystemConfigMultipleChangedEvent::class, $listener);
        $this->addEventListener($eventDispatcher, SystemConfigChangedHook::class, $listener);

        $this->systemConfigService->deleteExtensionConfiguration($extensionName, [
            ['elements' => [['name' => 'testSetting1'], ['name' => 'testSetting2']]],
        ]);

        // Reset the memoized values
        $this->getContainer()->get(MemoizedSystemConfigStore::class)->reset();

        // All records should be deleted
        static::assertNull($this->systemConfigService->get($configKey1));
        static::assertNull($this->systemConfigService->get($configKey1, TestDefaults::SALES_CHANNEL));
        static::assertFalse($this->systemConfigService->getBool($configKey2));
        static::assertFalse($this->systemConfigService->getBool($configKey2, TestDefaults::SALES_CHANNEL));

        // Assert that the events were dispatched correctly for the global scope
        static::assertCount(1, $dispatchedEvents[BeforeSystemConfigMultipleChangedEvent::class]['global']);
        static::assertCount(1, $dispatchedEvents[SystemConfigMultipleChangedEvent::class]['global']);
        static::assertCount(1, $dispatchedEvents[SystemConfigChangedHook::class]['global']);

        // Assert that the events were dispatched correctly for the sales channel scope
        static::assertCount(1, $dispatchedEvents[BeforeSystemConfigMultipleChangedEvent::class]['sales_channel']);
        static::assertCount(1, $dispatchedEvents[SystemConfigMultipleChangedEvent::class]['sales_channel']);
        static::assertCount(1, $dispatchedEvents[SystemConfigChangedHook::class]['sales_channel']);

        // Assert content of bulk events
        $globalMultipleEvent = $dispatchedEvents[SystemConfigMultipleChangedEvent::class]['global'][0];
        static::assertInstanceOf(SystemConfigMultipleChangedEvent::class, $globalMultipleEvent);
        static::assertEquals([$configKey1, $configKey2], array_keys($globalMultipleEvent->getConfig()));

        $salesChannelMultipleEvent = $dispatchedEvents[SystemConfigMultipleChangedEvent::class]['sales_channel'][0];
        static::assertInstanceOf(SystemConfigMultipleChangedEvent::class, $salesChannelMultipleEvent);
        static::assertEquals([$configKey1, $configKey2], array_keys($salesChannelMultipleEvent->getConfig()));
    }
}
