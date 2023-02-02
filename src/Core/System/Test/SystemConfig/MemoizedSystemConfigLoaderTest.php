<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Shopware\Core\System\SystemConfig\CachedSystemConfigLoader;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Shopware\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Shopware\Core\System\SystemConfig\SystemConfigLoader;
use Shopware\Core\System\Test\SystemConfig\_fixtures\MemoizedSystemConfigLoaderTest\DecoratedMemoizedResetTestSystemConfigLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class MemoizedSystemConfigLoaderTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMemoizationWithSalesChannelIdWorks(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::once())
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        // Ensure a second call does not call the load method and returns the same result.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationWithoutSalesChannelIdWorks(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::once())
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);

        // Ensure a second call does not call the load method and returns the same result.
        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsForSalesChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        // Reset config without sales channel ID.
        $configStore->removeConfig(TestDefaults::SALES_CHANNEL);

        // The load method is now called a second time as memoization has been reset for the sales channel.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsWithoutSalesChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);

        // Reset config without sales channel ID.
        $configStore->removeConfig(null);

        // The load method is now called a second time as the global memoization has been reset.
        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetWithoutSalesChannelIdForAllSalesChannels(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        // AS the global config is reset, all sales channels are reset and load is called a second time.
        $configStore->removeConfig(null);

        // The load method is now called a second time as the memoization has been reset for all sales channels.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationDoesNotResetOnResetForDifferentSalesChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(1))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        // Reset the config for a different sales channel ID.
        $configStore->removeConfig(Uuid::randomHex());

        // The load method is not called again as the config was reset for a different sales channel ID.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testUsingDifferentSalesChannelIdsCallsLoad(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        // Ensure that using a different sales channel ID calls the load method again.
        $config = $service->load(Uuid::randomHex());
        static::assertSame($expectedConfig, $config);
    }

    public function testUsingGlobalAndSalesChannelIdLoadCallsLoad(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);

        // Ensure that using a sales channel ID calls the load method again.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsOnValueChangeEventForSalesChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization and ensures load is called a second time.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', TestDefaults::SALES_CHANNEL));

        // The load method is now called a second time as memoization has been reset for the sales channel.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsOnValueChangeEventWithoutSalesChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization and ensures load is called a second time.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', null));

        // The load method is now called a second time as the global memoization has been reset.
        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsOnValueChangeEventWithoutSalesChannelIdForAllSalesChannels(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization for all sales channels and ensures load is called a second time.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', null));

        // The load method is now called a second time as the memoization has been reset for all sales channels.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationDoesNotResetOnValueChangedEventForDifferentSalesChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(1))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization for a different sales channel ID.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', Uuid::randomHex()));

        // The load method is not called again as the config was reset for a different sales channel ID.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsWhenCallingMethod(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        $configStore->reset();

        // The load method is now called a second time as memoization has been reset.
        $config = $service->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsOnValueChangeEventWithDecoratedSystemConfigLoader(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);
        $decorated = new DecoratedMemoizedResetTestSystemConfigLoader($service);

        $config = $decorated->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization and ensures load is called a second time.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', TestDefaults::SALES_CHANNEL));

        // The load method is now called a second time as memoization has been reset for the sales channel.
        $config = $decorated->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsWhenCallingMethodWithDecoratedSystemConfigLoader(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects(static::exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);
        $decorated = new DecoratedMemoizedResetTestSystemConfigLoader($service);

        $config = $decorated->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);

        $configStore->reset();

        // The load method is now called a second time as memoization has been reset.
        $config = $decorated->load(TestDefaults::SALES_CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testServiceDecorationChainPriority(): void
    {
        $service = $this->getContainer()->get(SystemConfigLoader::class);

        static::assertInstanceOf(MemoizedSystemConfigLoader::class, $service);
        static::assertInstanceOf(CachedSystemConfigLoader::class, $service->getDecorated());
        static::assertInstanceOf(SystemConfigLoader::class, $service->getDecorated()->getDecorated());
    }
}
