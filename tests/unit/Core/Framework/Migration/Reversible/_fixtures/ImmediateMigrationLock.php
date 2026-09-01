<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\MigrationLock;

/**
 * @internal
 */
#[Package('framework')]
final class ImmediateMigrationLock extends MigrationLock
{
    /**
     * @var list<string>
     */
    public array $acquired = [];

    public function __construct()
    {
    }

    public function synchronized(string $plugin, \Closure $callback): mixed
    {
        $this->acquired[] = $plugin;

        return $callback();
    }
}
