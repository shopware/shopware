<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * One version per MCP list (tools, resources, prompts), shared by every server through the database.
 * A capability change increments it; each MCP request compares it with the versions its session has
 * seen, see {@see McpListChangedNotifier::syncSession()}.
 */
#[Package('framework')]
class McpListVersions
{
    final public const TOOLS = 'tools';
    final public const RESOURCES = 'resources';
    final public const PROMPTS = 'prompts';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function bump(McpListChangedNotificationSet $lists): void
    {
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach (self::listsOf($lists) as $list) {
            $this->connection->executeStatement(
                'INSERT INTO `mcp_list_version` (`list`, `version`, `updated_at`) VALUES (:list, 1, :now)
                 ON DUPLICATE KEY UPDATE `version` = `version` + 1, `updated_at` = :now',
                ['list' => $list, 'now' => $now],
            );
        }
    }

    /**
     * @return array{tools: int, resources: int, prompts: int}
     */
    public function current(): array
    {
        $versions = [self::TOOLS => 0, self::RESOURCES => 0, self::PROMPTS => 0];

        /** @var array<string, string|int> $rows */
        $rows = $this->connection->fetchAllKeyValue('SELECT `list`, `version` FROM `mcp_list_version`');
        foreach ($rows as $list => $version) {
            if (\array_key_exists($list, $versions)) {
                $versions[$list] = (int) $version;
            }
        }

        return $versions;
    }

    /**
     * @return list<string>
     */
    private static function listsOf(McpListChangedNotificationSet $lists): array
    {
        return array_keys(array_filter([
            self::TOOLS => $lists->tools,
            self::RESOURCES => $lists->resources,
            self::PROMPTS => $lists->prompts,
        ]));
    }
}
