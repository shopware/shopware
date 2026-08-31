<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\ScheduledTask;

use Mcp\Server\Session\SessionStoreInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Removes abandoned mcp_toolset_session rows. Rows are normally deleted when the client sends
 * DELETE /api/_mcp, but a client that disconnects without a DELETE would otherwise leave its rows
 * behind forever. Cleanup is tied strictly to the MCP session store's own liveness: a row is
 * dropped only once its session no longer exists in the store. The store expires a session once it
 * has been idle past its TTL, so an active session (however old) is never purged, while an
 * abandoned one is reclaimed after it expires. created_at is deliberately not used as a delete
 * criterion, because an active session can outlive any fixed age.
 */
#[Package('framework')]
#[AsMessageHandler(handles: McpToolsetSessionCleanupTask::class)]
final class McpToolsetSessionCleanupTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly McpToolsetSessionStorage $sessionStorage,
        private readonly ?SessionStoreInterface $sessionStore = null,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $sessionStore = $this->sessionStore;

        // Without a session store we cannot determine liveness. MCP is then unavailable, so no
        // session can be alive to serve; leaving the rows in place is safe and they are reclaimed
        // once the store is back and their sessions have expired.
        if ($sessionStore === null) {
            return;
        }

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
