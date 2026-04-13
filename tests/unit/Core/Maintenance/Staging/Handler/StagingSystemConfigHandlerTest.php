<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\Staging\Handler\StagingSystemConfigHandler;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(StagingSystemConfigHandler::class)]
class StagingSystemConfigHandlerTest extends TestCase
{
    public function testEmptyOverrides(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $this->createMock(SymfonyStyle::class),
            true,
            [],
            [],
        ));

        static::assertNull($config->get('core.someKey'));
    }

    public function testSetsDefaultConfigOverrides(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->exactly(2))->method('info');

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $io,
            true,
            [],
            [],
            [
                'default' => [
                    'core.someKey' => 'someValue',
                    'core.anotherKey' => true,
                ],
            ],
        ));

        static::assertSame('someValue', $config->get('core.someKey'));
        static::assertTrue($config->get('core.anotherKey'));
    }

    public function testSetsSalesChannelConfigOverrides(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->exactly(2))->method('info');

        $salesChannelId = 'a1b2c3d4e5f6';

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $io,
            true,
            [],
            [],
            [
                $salesChannelId => [
                    'core.someKey' => 'channelValue',
                    'core.anotherKey' => 42,
                ],
            ],
        ));

        static::assertSame('channelValue', $config->get('core.someKey', $salesChannelId));
        static::assertSame(42, $config->get('core.anotherKey', $salesChannelId));
    }

    public function testSetsDefaultAndSalesChannelOverrides(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->exactly(3))->method('info');

        $salesChannelId = 'a1b2c3d4e5f6';

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $io,
            true,
            [],
            [],
            [
                'default' => [
                    'core.someKey' => 'globalValue',
                ],
                $salesChannelId => [
                    'core.someKey' => 'channelValue',
                    'core.anotherKey' => 42,
                ],
            ],
        ));

        static::assertSame('globalValue', $config->get('core.someKey'));
        static::assertSame('channelValue', $config->get('core.someKey', $salesChannelId));
        static::assertSame(42, $config->get('core.anotherKey', $salesChannelId));
    }

    public function testSetsOverridesForMultipleSalesChannels(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->exactly(4))->method('info');

        $channelOne = 'a1b2c3d4e5f6';
        $channelTwo = 'f6e5d4c3b2a1';

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $io,
            true,
            [],
            [],
            [
                'default' => [
                    'core.sharedKey' => 'global',
                ],
                $channelOne => [
                    'core.sharedKey' => 'channel-one-value',
                    'core.uniqueKey' => 'only-channel-one',
                ],
                $channelTwo => [
                    'core.sharedKey' => 'channel-two-value',
                ],
            ],
        ));

        static::assertSame('global', $config->get('core.sharedKey'));
        static::assertSame('channel-one-value', $config->get('core.sharedKey', $channelOne));
        static::assertSame('only-channel-one', $config->get('core.uniqueKey', $channelOne));
        static::assertSame('channel-two-value', $config->get('core.sharedKey', $channelTwo));
        static::assertNull($config->get('core.uniqueKey', $channelTwo));
    }
}
