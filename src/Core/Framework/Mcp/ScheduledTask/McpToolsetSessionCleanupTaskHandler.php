<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\ScheduledTask;

use Mcp\Server\Session\SessionStoreInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @internal
 *
 * Removes abandoned mcp_toolset_session rows. Rows are normally deleted when the client sends
 * DELETE /api/_mcp, but a client that disconnects without a DELETE would otherwise leave its rows
 * behind forever. This task purges them, tying the cleanup to the MCP session store's own expiry.
 */
#[AsMessageHandler(handles: McpToolsetSessionCleanupTask::class)]
#[Package('framework')]
final class McpToolsetSessionCleanupTaskHandler extends ScheduledTaskHandler
{
    /**
     * Rows older than this can never belong to a live MCP session (the session store TTL defaults
     * to one hour), so they are safe to purge by created_at even when the session store cannot be
     * resolved. This is a generous safety net; the precise cleanup below runs against the store.
     */
    private const HARD_RETENTION = 'P1D';

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly McpToolsetSessionStorage $sessionStorage,
        private readonly ClockInterface $clock,
        private readonly ?SessionStoreInterface $sessionStore = null,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        // Safety net: drop rows far older than any possible live session, independent of the store.
        $this->sessionStorage->deleteCreatedBefore($this->clock->now()->sub(new \DateInterval(self::HARD_RETENTION)));

        $sessionStore = $this->sessionStore;
        if ($sessionStore === null) {
            return;
        }

        // Precise cleanup: drop rows whose MCP session has already expired from the session store.
        foreach ($this->sessionStorage->sessionIds() as $sessionId) {
            try {
                $uuid = Uuid::fromString($sessionId);
            } catch (\InvalidArgumentException) {
                $this->sessionStorage->deleteForSession($sessionId);

                continue;
            }

            if (!$sessionStore->exists($uuid)) {
                $this->sessionStorage->deleteForSession($sessionId);
            }
        }
    }
}
