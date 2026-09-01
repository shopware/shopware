<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ExecutedMigration
{
    /**
     * @param class-string<Migration> $class
     */
    public function __construct(
        public string $class,
        public int $creationTimestamp,
    ) {
    }
}
