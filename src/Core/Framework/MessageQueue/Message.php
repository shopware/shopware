<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Symfony\Component\EventDispatcher\Event;

class Message extends Event
{
    /**
     * @var string
     */
    protected $eventName;

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }
}
