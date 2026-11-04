<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type VersionHistoryEntry array{version: string, recordedAt: string, installed: bool}
 */
#[Package('framework')]
final readonly class VersionHistory
{
    final public const STORAGE_KEY = 'core.versionHistory';

    private const MAX_ENTRIES = 24;

    public function __construct(
        private AbstractKeyValueStorage $storage,
        private ClockInterface $clock,
        private string $shopwareVersion,
    ) {
    }

    public function record(bool $installed): void
    {
        $entries = $installed ? [] : $this->all();

        if (\in_array($this->shopwareVersion, array_column($entries, 'version'), true)) {
            return;
        }

        $entries[] = [
            'version' => $this->shopwareVersion,
            'recordedAt' => $this->clock->now()->format(\DateTimeInterface::ATOM),
            'installed' => $installed,
        ];

        $this->storage->set(
            self::STORAGE_KEY,
            \array_slice($entries, -self::MAX_ENTRIES)
        );
    }

    /**
     * @return list<VersionHistoryEntry>
     */
    public function all(): array
    {
        $stored = $this->storage->get(self::STORAGE_KEY, []);

        if (\is_string($stored)) {
            $stored = \json_decode($stored, true);
        }

        if (!\is_array($stored)) {
            return [];
        }

        /** @var list<VersionHistoryEntry> $stored */
        return $stored;
    }
}
