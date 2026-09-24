<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\ScheduledTask;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Removes abandoned mcp_tool_result_cache rows by age. Rows are normally deleted when the
 * client sends DELETE /api/_mcp or DELETE /store-api/_mcp, but a client that disconnects
 * without a DELETE would otherwise leave full tool payloads behind forever. Age-based TTL
 * is used deliberately (unlike mcp_toolset_session, which keys off session-store liveness):
 * a stored result is only read during the call that produced it and the model's immediate
 * follow-up, and the 2026-07-28 modern era has no durable session store / DELETE returns
 * 405 — so cleanup must not assume a SessionStoreInterface exists.
 */
#[Package('framework')]
#[AsMessageHandler(handles: McpToolResultCacheCleanupTask::class)]
final class McpToolResultCacheCleanupTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly ToolResultCacheStorage $storage,
        private readonly ClockInterface $clock,
        private readonly int $ttlSeconds = ToolResultCacheStorage::DEFAULT_TTL_SECONDS,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $threshold = $this->clock->now()->modify(\sprintf('-%d seconds', $this->ttlSeconds));

        $this->storage->deleteOlderThan($threshold);
    }
}
