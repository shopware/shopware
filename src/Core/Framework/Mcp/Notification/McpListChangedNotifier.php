<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Mcp\Schema\Notification\PromptListChangedNotification;
use Mcp\Schema\Notification\ResourceListChangedNotification;
use Mcp\Schema\Notification\ToolListChangedNotification;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Exception\JsonDecodingException;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Uid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Queues `list_changed` notifications in a session's outgoing queue in the session store; the client
 * receives them with its next request. Changes for every session are pulled, not pushed: notify()
 * bumps a shared list version and syncSession() queues the notification when a session sees a version
 * it has not seen yet. See "List change notifications" in `Mcp/AGENTS.md`.
 */
#[Package('framework')]
class McpListChangedNotifier
{
    /**
     * Request attribute a tool sets to ask its MCP controller to emit a tools/listChanged for the
     * current session. The controller flushes it only after the SDK has persisted its in-memory
     * session (see notifySession()), so the queued notification is not overwritten.
     */
    final public const PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE = 'shopware.mcp.pending_tools_list_changed';

    private const SESSION_OUTGOING_QUEUE = '_mcp';
    private const SESSION_OUTGOING_QUEUE_KEY = 'outgoing_queue';

    /**
     * Session data key holding the list versions the session has been notified about.
     */
    private const SESSION_SEEN_VERSIONS = 'shopware_list_versions';

    /**
     * @param McpListVersions|null $listVersions null for a server whose lists only change per session
     */
    public function __construct(
        private readonly ?SessionStoreInterface $sessionStore,
        private readonly ?McpListVersions $listVersions = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Announces an installation-wide capability change (e.g. app install or uninstall) to every
     * session, on every server. Costs one write; each session picks the change up on its next request.
     */
    public function notify(McpListChangedNotificationSet $notifications): void
    {
        if (!$notifications->hasChanges()) {
            return;
        }

        $this->listVersions?->bump($notifications);
    }

    /**
     * Queues `list_changed` for the lists whose version moved since this session last looked. A new
     * session starts at the current versions, so it gets no notification for changes before it existed.
     *
     * Must run after the MCP SDK has persisted its in-memory session, like notifySession().
     */
    public function syncSession(string $sessionId): void
    {
        if ($this->sessionStore === null || $this->listVersions === null) {
            return;
        }

        $session = $this->readSession($sessionId, $this->sessionStore);
        if ($session === null) {
            return;
        }

        [$uuid, $sessionData] = $session;
        $current = $this->listVersions->current();
        $seen = $sessionData[self::SESSION_SEEN_VERSIONS] ?? null;

        if ($seen === $current) {
            return;
        }

        if (\is_array($seen)) {
            $sessionData = $this->appendToQueue($sessionData, $this->buildMessages(new McpListChangedNotificationSet(
                tools: ($seen[McpListVersions::TOOLS] ?? null) !== $current[McpListVersions::TOOLS],
                resources: ($seen[McpListVersions::RESOURCES] ?? null) !== $current[McpListVersions::RESOURCES],
                prompts: ($seen[McpListVersions::PROMPTS] ?? null) !== $current[McpListVersions::PROMPTS],
            )));
        }

        $sessionData[self::SESSION_SEEN_VERSIONS] = $current;
        $this->sessionStore->write($uuid, Json::encode($sessionData));
    }

    /**
     * Queues a list_changed notification for a single MCP session. Use this for session-local
     * changes (e.g. enabling a toolset for the current session).
     *
     * This writes directly to the session store, so for the session of the current request it must
     * be called AFTER the MCP SDK has persisted its in-memory session (i.e. after the server run
     * completes); otherwise the SDK's own save overwrites the queued notification. Callers inside a
     * tool must defer via {@see self::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE} instead of calling this
     * during the tool invocation.
     */
    public function notifySession(string $sessionId, McpListChangedNotificationSet $notifications): void
    {
        if ($this->sessionStore === null || !$notifications->hasChanges()) {
            return;
        }

        $session = $this->readSession($sessionId, $this->sessionStore);
        if ($session === null) {
            return;
        }

        [$uuid, $sessionData] = $session;
        $this->sessionStore->write($uuid, Json::encode($this->appendToQueue($sessionData, $this->buildMessages($notifications))));
    }

    /**
     * @return array{Uuid, array<string, mixed>}|null
     */
    private function readSession(string $sessionId, SessionStoreInterface $sessionStore): ?array
    {
        try {
            $uuid = Uuid::fromString($sessionId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (!$sessionStore->exists($uuid)) {
            return null;
        }

        try {
            $rawSession = $sessionStore->read($uuid);
            $sessionData = $rawSession !== false ? Json::decodeToArray($rawSession) : [];
        } catch (JsonDecodingException $exception) {
            $this->logger->warning('Skipping MCP list_changed notification for unreadable session data.', [
                'sessionId' => $sessionId,
                'exception' => $exception,
            ]);

            return null;
        }

        return [$uuid, $sessionData];
    }

    /**
     * @param array<string, mixed> $sessionData
     * @param list<string> $messages
     *
     * @return array<string, mixed>
     */
    private function appendToQueue(array $sessionData, array $messages): array
    {
        $mcpData = $sessionData[self::SESSION_OUTGOING_QUEUE] ?? [];
        if (!\is_array($mcpData)) {
            $mcpData = [];
        }

        $queue = $mcpData[self::SESSION_OUTGOING_QUEUE_KEY] ?? [];
        if (!\is_array($queue)) {
            $queue = [];
        }

        foreach ($messages as $message) {
            $queue[] = [
                'message' => $message,
                'context' => ['type' => 'notification'],
            ];
        }

        $mcpData[self::SESSION_OUTGOING_QUEUE_KEY] = $queue;
        $sessionData[self::SESSION_OUTGOING_QUEUE] = $mcpData;

        return $sessionData;
    }

    /**
     * @return list<string>
     */
    private function buildMessages(McpListChangedNotificationSet $notifications): array
    {
        $messages = [];

        if ($notifications->tools) {
            $messages[] = $this->createNotification(ToolListChangedNotification::getMethod());
        }

        if ($notifications->resources) {
            $messages[] = $this->createNotification(ResourceListChangedNotification::getMethod());
        }

        if ($notifications->prompts) {
            $messages[] = $this->createNotification(PromptListChangedNotification::getMethod());
        }

        return $messages;
    }

    private function createNotification(string $method): string
    {
        return Json::encode([
            'jsonrpc' => '2.0',
            'method' => $method,
        ]);
    }
}
