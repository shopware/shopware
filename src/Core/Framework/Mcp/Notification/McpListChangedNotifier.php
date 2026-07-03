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
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class McpListChangedNotifier
{
    private const SESSION_OUTGOING_QUEUE = '_mcp';
    private const SESSION_OUTGOING_QUEUE_KEY = 'outgoing_queue';

    public function __construct(
        private readonly ?SessionStoreInterface $sessionStore,
        private readonly McpSessionRegistry $sessionRegistry,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function notify(McpListChangedNotificationSet $notifications): void
    {
        $sessionStore = $this->sessionStore;
        if ($sessionStore === null || !$notifications->hasChanges()) {
            return;
        }

        $messages = $this->buildMessages($notifications);

        foreach ($this->sessionRegistry->all() as $sessionId) {
            $this->queueForSession($sessionId, $messages, $sessionStore);
        }
    }

    /**
     * @param list<string> $messages
     */
    private function queueForSession(string $sessionId, array $messages, SessionStoreInterface $sessionStore): void
    {
        try {
            $uuid = Uuid::fromString($sessionId);
        } catch (\InvalidArgumentException) {
            $this->sessionRegistry->remove($sessionId);

            return;
        }

        if (!$sessionStore->exists($uuid)) {
            $this->sessionRegistry->remove($sessionId);

            return;
        }

        try {
            $rawSession = $sessionStore->read($uuid);
            $sessionData = $rawSession !== false ? Json::decodeToArray($rawSession) : [];
        } catch (JsonDecodingException $exception) {
            $this->logger->warning('Skipping MCP list_changed notification for unreadable session data.', [
                'sessionId' => $sessionId,
                'exception' => $exception,
            ]);

            return;
        }

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
        $sessionStore->write($uuid, Json::encode($sessionData));
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
