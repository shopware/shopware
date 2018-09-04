<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Event;

use Symfony\Component\EventDispatcher\Event;

class MigrateFinishEvent extends Event
{
    public const EVENT_NAME = 'migration.migrate.finish';

    /**
     * @var int
     */
    private $migrated;

    /**
     * @var int
     */
    private $total;

    public function __construct(int $migrated = 0, int $total = 0)
    {
        $this->migrated = $migrated;
        $this->total = $total;
    }

    public function getMigrated(): int
    {
        return $this->migrated;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
