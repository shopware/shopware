<?php

namespace Shopware\Media\Event;

use Symfony\Component\EventDispatcher\Event;

class MigrateStartEvent extends Event
{
    const EVENT_NAME = 'media.migrate.start';

    /**
     * @var int
     */
    private $numberOfFiles;

    public function __construct(int $numberOfFiles = 0)
    {
        $this->numberOfFiles = $numberOfFiles;
    }

    /**
     * @return int
     */
    public function getNumberOfFiles(): int
    {
        return $this->numberOfFiles;
    }
}
