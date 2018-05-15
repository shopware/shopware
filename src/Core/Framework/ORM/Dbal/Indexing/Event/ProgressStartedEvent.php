<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing\Event;

use Symfony\Component\EventDispatcher\Event;

class ProgressStartedEvent extends Event
{
    public const NAME = 'indexing.progress.started';

    /**
     * @var string
     */
    protected $message;

    /**
     * @var int
     */
    protected $total;

    public function __construct(string $message, int $total)
    {
        $this->message = $message;
        $this->total = $total;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
