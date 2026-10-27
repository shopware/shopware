<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Mcp\Schema\JsonRpc\Notification;
use Mcp\Schema\Notification\PromptListChangedNotification;
use Mcp\Schema\Notification\ResourceListChangedNotification;
use Mcp\Schema\Notification\ToolListChangedNotification;
use Mcp\Server\Subscription\NotificationBusInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Carries list changes to the `subscriptions/listen` streams of the stateless era, using the list versions in
 * `mcp_list_version` ({@see McpListVersions}) as the bus. There is no backlog of notifications: the versions are
 * the state, and a stream only needs to know which of the three lists moved since it last looked.
 *
 * - publish() bumps the version of the list the notification names, so the SDK's registry events, app lifecycle
 *   changes and allowlist edits all end up in the same table. Notifications that name no list are dropped.
 * - cursor() packs the three versions into one integer, since() emits `list_changed` for each list whose
 *   version differs from the one packed into the cursor.
 * - Streams poll since() about four times a second. The versions are read through a `cache.app` item that
 *   lives one second, so each host reads the table at most once a second, however many streams are open.
 *
 * Unlike a cache-backed bus, this reaches the CLI and queue workers (`app:install`) and every host that shares
 * the database, and concurrent publishers cannot lose a change: the bump is one atomic statement.
 */
#[Package('framework')]
final class McpListVersionsNotificationBus implements NotificationBusInterface
{
    final public const CACHE_KEY = 'mcp-list-versions';

    /**
     * Seconds the versions are cached, which is also how long a change can take to reach an open stream.
     */
    final public const CACHE_TTL = 1;

    /**
     * Bits of each version kept in the cursor. Three times 21 bits fit into a non-negative 64-bit integer. Only
     * equality is compared, so a version above 2^21 wraps without harm.
     */
    private const BITS = 21;

    private const MASK = (1 << self::BITS) - 1;

    /**
     * @internal
     */
    public function __construct(
        private readonly McpListVersions $listVersions,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(Notification $notification): void
    {
        $lists = match (true) {
            $notification instanceof ToolListChangedNotification => new McpListChangedNotificationSet(tools: true, resources: false, prompts: false),
            $notification instanceof ResourceListChangedNotification => new McpListChangedNotificationSet(tools: false, resources: true, prompts: false),
            $notification instanceof PromptListChangedNotification => new McpListChangedNotificationSet(tools: false, resources: false, prompts: true),
            default => null,
        };

        if ($lists === null) {
            $this->logger->notice('Dropped an MCP notification the list version bus cannot carry.', [
                'method' => $notification::getMethod(),
            ]);

            return;
        }

        $this->listVersions->bump($lists);
    }

    public function cursor(): int
    {
        return self::pack($this->versions());
    }

    /**
     * A failing read keeps the stream open at the same cursor, so the change is delivered once the table can be
     * read again.
     */
    public function since(int $cursor): array
    {
        try {
            $versions = $this->versions();
        } catch (\Throwable $exception) {
            $this->logger->warning('Could not read the MCP list versions for a subscriptions/listen stream.', [
                'exception' => $exception,
            ]);

            return [[], $cursor];
        }

        $next = self::pack($versions);
        if ($next === $cursor) {
            return [[], $cursor];
        }

        $notifications = [];

        if (self::moved($cursor, $next, 2)) {
            $notifications[] = new ToolListChangedNotification();
        }

        if (self::moved($cursor, $next, 1)) {
            $notifications[] = new ResourceListChangedNotification();
        }

        if (self::moved($cursor, $next, 0)) {
            $notifications[] = new PromptListChangedNotification();
        }

        return [$notifications, $next];
    }

    /**
     * @return array{tools: int, resources: int, prompts: int}
     */
    private function versions(): array
    {
        $versions = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->listVersions->current();
        });

        return [
            McpListVersions::TOOLS => self::version($versions, McpListVersions::TOOLS),
            McpListVersions::RESOURCES => self::version($versions, McpListVersions::RESOURCES),
            McpListVersions::PROMPTS => self::version($versions, McpListVersions::PROMPTS),
        ];
    }

    private static function version(mixed $versions, string $list): int
    {
        $version = \is_array($versions) ? ($versions[$list] ?? 0) : 0;

        return \is_int($version) ? $version : 0;
    }

    /**
     * @param array{tools: int, resources: int, prompts: int} $versions
     */
    private static function pack(array $versions): int
    {
        return (($versions[McpListVersions::TOOLS] & self::MASK) << (2 * self::BITS))
            | (($versions[McpListVersions::RESOURCES] & self::MASK) << self::BITS)
            | ($versions[McpListVersions::PROMPTS] & self::MASK);
    }

    private static function moved(int $from, int $to, int $slot): bool
    {
        $shift = $slot * self::BITS;

        return (($from >> $shift) & self::MASK) !== (($to >> $shift) & self::MASK);
    }
}
