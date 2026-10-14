<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ScheduledTask\McpToolResultCacheCleanupTaskHandler;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultCacheCleanupTaskHandler::class)]
class McpToolResultCacheCleanupTaskHandlerTest extends TestCase
{
    public function testRunDeletesRowsOlderThanDefaultTtl(): void
    {
        $now = new \DateTimeImmutable('2026-09-24 12:00:00');
        $clock = new MockClock($now);

        $expectedThreshold = $now->modify(\sprintf('-%d seconds', ToolResultCacheStorage::DEFAULT_TTL_SECONDS));

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->once())
            ->method('deleteOlderThan')
            ->with(static::callback(static function (\DateTimeInterface $threshold) use ($expectedThreshold): bool {
                return $threshold->format('Y-m-d H:i:s') === $expectedThreshold->format('Y-m-d H:i:s');
            }));

        $handler = new McpToolResultCacheCleanupTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $storage,
            $clock,
            new NullLogger(),
        );

        $handler->run();
    }

    public function testRunHonoursTheConfiguredTtlAndLogsTheOutcome(): void
    {
        $now = new \DateTimeImmutable('2026-09-24 12:00:00');
        $clock = new MockClock($now);
        $ttlSeconds = 3600;

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->once())
            ->method('deleteOlderThan')
            ->with(static::callback(static function (\DateTimeInterface $threshold) use ($now, $ttlSeconds): bool {
                return $threshold->format('Y-m-d H:i:s') === $now->modify(\sprintf('-%d seconds', $ttlSeconds))->format('Y-m-d H:i:s');
            }))
            ->willReturn(7);

        $mcpLogger = $this->createMock(LoggerInterface::class);
        $mcpLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::INFO, 'Removed expired MCP tool results', static::callback(static fn (array $context): bool => $context['deleted'] === 7));

        $handler = new McpToolResultCacheCleanupTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $storage,
            $clock,
            $mcpLogger,
            $ttlSeconds,
        );

        $handler->run();
    }
}
