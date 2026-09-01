<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\ExecutedMigration;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\MigrationStateStore;

/**
 * @internal
 */
#[Package('framework')]
final class InMemoryMigrationStateStore extends MigrationStateStore
{
    /**
     * @var array<string, array<string, int>>
     */
    private array $state = [];

    public function __construct()
    {
    }

    public function executed(string $plugin): array
    {
        $entries = $this->state[$plugin] ?? [];
        asort($entries);

        $executed = [];
        foreach ($entries as $class => $timestamp) {
            /** @var class-string<Migration> $class */
            $executed[] = new ExecutedMigration($class, $timestamp);
        }

        return $executed;
    }

    public function markExecuted(string $plugin, string $class, int $creationTimestamp): void
    {
        $this->state[$plugin][$class] = $creationTimestamp;
    }

    public function remove(string $plugin, string $class): void
    {
        unset($this->state[$plugin][$class]);
    }

    /**
     * @return array<string, int>
     */
    public function timestampsFor(string $plugin): array
    {
        return $this->state[$plugin] ?? [];
    }
}
