<?php

namespace Shopware\Translation\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Finder\SplFileInfo;

class ImportAdvanceEvent extends Event
{
    const EVENT_NAME = 'translation.import.advance';

    /**
     * @var string
     */
    private $file;

    public function __construct(SplFileInfo $file)
    {
        $this->file = $file;
    }

    public function getFile(): string
    {
        return $this->file;
    }
}