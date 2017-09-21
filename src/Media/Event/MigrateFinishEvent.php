<?php

namespace Shopware\Media\Event;

use Symfony\Component\EventDispatcher\Event;

class MigrateFinishEvent extends Event
{
    const EVENT_NAME = 'media.migrate.finish';
    /**
     * @var int
     */
    private $migrated;

    /**
     * @var int
     */
    private $skipped;

    public function __construct(int $migrated = 0, int $skipped = 0)
    {
        $this->migrated = $migrated;
        $this->skipped = $skipped;
    }

    /**
     * @return int
     */
    public function getMigrated(): int
    {
        return $this->migrated;
    }

    /**
     * @return int
     */
    public function getSkipped(): int
    {
        return $this->skipped;
    }
}
