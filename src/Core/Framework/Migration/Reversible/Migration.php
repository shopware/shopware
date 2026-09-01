<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible;

use Shopware\Core\Framework\Log\Package;

/**
 * Base class for reversible plugin migrations.
 *
 * Unlike MigrationStep, every migration declares how to undo itself. up() runs before plugin install
 * and update, down() runs after a destructive plugin uninstall, in reverse order.
 *
 * Implementations must be instantiable without constructor arguments and must keep their creation
 * timestamp stable once applied: down() is loaded from that historical class during uninstall.
 */
#[Package('framework')]
abstract class Migration
{
    /**
     * Unique, positive, and never changed after the migration has been applied.
     */
    abstract public function getCreationTimestamp(): int;

    /**
     * MySQL implicitly commits most DDL, so a chain of migrations cannot be wrapped in one
     * transaction. Keep this method self-contained and retryable, and guard DDL with IF EXISTS.
     */
    abstract public function up(UpMigrationContext $context): void;

    /**
     * Runs while the plugin is being uninstalled, so it must not lazily load other plugin files.
     * It can restore the schema shape, but not rows destroyed by up().
     */
    abstract public function down(DownMigrationContext $context): void;
}
