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
 * behind forever. Cleanup is tied strictly to the MCP session stores' own liveness: a row is
 * dropped only once its session no longer exists in any of them. A store expires a session once it
 * has been idle past its TTL, so an active session (however old) is never purged, while an
 * abandoned one is reclaimed after it expires. created_at is deliberately not used as a delete
 * criterion, because an active session can outlive any fixed age.
 *
 * Rows are keyed on the raw Mcp-Session-Id and are not namespaced per endpoint, while each MCP
 * server owns its own session store. Every store therefore has to be consulted — checking only the
 * Admin API store would treat every live Store API session as abandoned and drop its toolsets.
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
        private readonly ?SessionStoreInterface $storeApiSessionStore = null,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $sessionStores = array_values(array_filter([$this->sessionStore, $this->storeApiSessionStore]));

        // Without a session store we cannot determine liveness. MCP is then unavailable, so no
        // session can be alive to serve; leaving the rows in place is safe and they are reclaimed
        // once the store is back and their sessions have expired.
        if ($sessionStores === []) {
            return;
        }

        foreach ($this->sessionStorage->sessionIds() as $sessionId) {
            try {
                $uuid = Uuid::fromString($sessionId);
            } catch (\InvalidArgumentException) {
                $this->sessionStorage->deleteForSession($sessionId);

                continue;
            }

            foreach ($sessionStores as $sessionStore) {
                if ($sessionStore->exists($uuid)) {
                    continue 2;
                }
            }

            $this->sessionStorage->deleteForSession($sessionId);
        }
    }
}
