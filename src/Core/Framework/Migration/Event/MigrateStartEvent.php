<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Event;

use Symfony\Component\EventDispatcher\Event;

class MigrateStartEvent extends Event
{
    public const EVENT_NAME = 'migration.migrate.start';

    /**
     * @var int
     */
    private $numberMigrations;

    public function __construct(int $numberMigrations = 0)
    {
        $this->numberMigrations = $numberMigrations;
    }

    public function getNumberOfMigrations(): int
    {
        return $this->numberMigrations;
    }
}
