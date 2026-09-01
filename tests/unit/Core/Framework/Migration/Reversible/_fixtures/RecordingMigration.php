<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * Records the order in which up() and down() were called into a shared log.
 *
 * The runner keys its state by class name, so tests that need more than one migration use the
 * distinct subclasses below rather than several instances of one class.
 *
 * @internal
 */
#[Package('framework')]
abstract class RecordingMigration extends Migration
{
    public ?UpMigrationContext $lastUpContext = null;

    public ?DownMigrationContext $lastDownContext = null;

    /**
     * @param \ArrayObject<int, string> $calls
     */
    public function __construct(
        private readonly int $timestamp,
        private readonly \ArrayObject $calls,
    ) {
    }

    public function getCreationTimestamp(): int
    {
        return $this->timestamp;
    }

    public function up(UpMigrationContext $context): void
    {
        $this->lastUpContext = $context;
        $this->calls->append(static::class . '::up');
    }

    public function down(DownMigrationContext $context): void
    {
        $this->lastDownContext = $context;
        $this->calls->append(static::class . '::down');
    }
}
