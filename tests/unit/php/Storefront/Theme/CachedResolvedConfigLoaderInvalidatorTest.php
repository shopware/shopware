<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Theme\CachedResolvedConfigLoaderInvalidator;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\CachedResolvedConfigLoaderInvalidator
 */
class CachedResolvedConfigLoaderInvalidatorTest extends TestCase
{
    private CachedResolvedConfigLoaderInvalidator $cachedResolvedConfigLoaderInvalidator;

    /**
     * @var Connection&\PHPUnit\Framework\MockObject\MockObject
     */
    private $connection;

    /**
     * @var CacheInvalidator&\PHPUnit\Framework\MockObject\MockObject
     */
    private $logger;

    public function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(CacheInvalidator::class);
        $this->cachedResolvedConfigLoaderInvalidator = new CachedResolvedConfigLoaderInvalidator(
            $this->logger,
            $this->connection
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            [
                ThemeConfigChangedEvent::class => 'invalidate',
                ThemeAssignedEvent::class => 'assigned',
                ThemeConfigResetEvent::class => 'reset',
            ],
            CachedResolvedConfigLoaderInvalidator::getSubscribedEvents()
        );
    }

    public function testAssigned(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $event = new ThemeAssignedEvent($themeId, $salesChannelId);
        $name = 'theme-config-' . $themeId;

        $snippetSetIds = [Uuid::randomHex(), Uuid::randomHex()];
        $this->connection->expects(static::once())->method('fetchFirstColumn')->willReturn($snippetSetIds);

        $snippetSetCacheKeys = array_map(function (string $setId) {
            return 'translation.catalog.' . $setId;
        }, $snippetSetIds);

        $expectedInvalidatedTags = [
            [[$name]],
            [[CachedDomainLoader::CACHE_KEY]],
            [$snippetSetCacheKeys],
        ];

        $this->logger->expects(static::exactly(\count($expectedInvalidatedTags)))->method('invalidate')->withConsecutive(...$expectedInvalidatedTags);
        $this->cachedResolvedConfigLoaderInvalidator->assigned($event);
    }
}
