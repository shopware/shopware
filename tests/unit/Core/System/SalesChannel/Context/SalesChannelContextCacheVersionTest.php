<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextCacheVersion;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelContextCacheVersion::class)]
class SalesChannelContextCacheVersionTest extends TestCase
{
    /**
     * @var CacheInterface&MockObject
     */
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
    }

    public function testGetsCurrentVersion(): void
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->with('sales-channel-context-version', static::isCallable())
            ->willReturn('version');

        $version = new SalesChannelContextCacheVersion($this->cache);

        static::assertSame('version', $version->get());
    }

    public function testInvalidatesVersion(): void
    {
        $this->cache->expects($this->once())
            ->method('delete')
            ->with('sales-channel-context-version');

        $version = new SalesChannelContextCacheVersion($this->cache);

        $version->invalidate();
    }
}
