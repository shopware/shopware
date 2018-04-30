<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Event;

use Symfony\Component\EventDispatcher\Event;

class ProgressFinishedEvent extends Event
{
    public const NAME = 'indexing.progress.finished';

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
