<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileCacheInvalidator::class)]
class SalesChannelFileCacheInvalidatorTest extends TestCase
{
    public function testItInvalidatesSalesChannelFileIdTagsForWrites(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([
                SalesChannelFileCacheInvalidator::buildCacheTag($firstId),
                SalesChannelFileCacheInvalidator::buildCacheTag($secondId),
            ], true);

        $event = new EntityWrittenEvent('sales_channel_file', [
            new EntityWriteResult($firstId, [
                'salesChannelId' => Uuid::randomHex(),
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
            ], 'sales_channel_file', EntityWriteResult::OPERATION_UPDATE),
            new EntityWriteResult($secondId, [], 'sales_channel_file', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext());

        (new SalesChannelFileCacheInvalidator($cacheInvalidator))->invalidate($event);
    }

    public function testItInvalidatesSalesChannelFileIdTagsForDeletes(): void
    {
        $id = Uuid::randomHex();
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([SalesChannelFileCacheInvalidator::buildCacheTag($id)], true);

        $event = new EntityDeletedEvent('sales_channel_file', [
            new EntityWriteResult($id, [], 'sales_channel_file', EntityWriteResult::OPERATION_DELETE),
        ], Context::createDefaultContext());

        (new SalesChannelFileCacheInvalidator($cacheInvalidator))->invalidate($event);
    }

    public function testItBuildsSalesChannelFileIdCacheTag(): void
    {
        static::assertSame('sales-channel-file-example-id', SalesChannelFileCacheInvalidator::buildCacheTag('example-id'));
    }

    public function testItInvalidatesDiscoveryTag(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([SalesChannelFileCacheInvalidator::buildDiscoveryCacheTag()], true);

        (new SalesChannelFileCacheInvalidator($cacheInvalidator))->invalidateDiscovery();
    }

    public function testItSubscribesToTemplateDiscoveryInvalidationEvents(): void
    {
        $events = SalesChannelFileCacheInvalidator::getSubscribedEvents();

        foreach ([
            AppActivatedEvent::class,
            AppDeactivatedEvent::class,
            AppUpdatedEvent::class,
            PluginPostActivateEvent::class,
            PluginPostDeactivateEvent::class,
            PluginPostUpdateEvent::class,
            UpdatePostFinishEvent::class,
        ] as $event) {
            static::assertSame('invalidateDiscovery', $events[$event]);
        }
    }
}
