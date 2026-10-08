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
 * Removes mcp_tool_result_cache rows older than `shopware.mcp.tool_result_cache_ttl`. DELETE of a
 * session removes its rows too, but a client that never sends it would leave full tool results
 * behind, and the stateless era has no DELETE at all. Age is used instead of session liveness
 * (unlike mcp_toolset_session), because a stored result is only read right after the call that
 * produced it, so no session store is needed.
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
        private readonly LoggerInterface $mcpLogger,
        private readonly int $ttlSeconds = ToolResultCacheStorage::DEFAULT_TTL_SECONDS,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $threshold = $this->clock->now()->modify(\sprintf('-%d seconds', $this->ttlSeconds));

        $deleted = $this->storage->deleteOlderThan($threshold);

        $this->mcpLogger->info('Removed expired MCP tool results', [
            'deleted' => $deleted,
            'threshold' => $threshold->format(\DateTimeInterface::ATOM),
        ]);
    }
}
