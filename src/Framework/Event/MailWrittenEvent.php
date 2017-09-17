<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class MailWrittenEvent extends NestedEvent
{
    const NAME = 'mail.written';

    /**
     * @var string[]
     */
    private $mailUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $mailUuids, array $errors = [])
    {
        $this->mailUuids = $mailUuids;
        $this->events = new NestedEventCollection();
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string[]
     */
    public function getMailUuids(): array
    {
        return $this->mailUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}
