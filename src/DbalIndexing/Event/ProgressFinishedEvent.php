<?php

namespace Shopware\DbalIndexing\Event;

use Symfony\Component\EventDispatcher\Event;

class ProgressFinishedEvent extends Event
{
    const NAME = 'indexing.progress.finished';

    /**
     * @var string
     */
    protected $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
