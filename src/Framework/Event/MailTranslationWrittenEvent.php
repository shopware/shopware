<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class MailTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'mail_translation.written';

    /**
     * @var string[]
     */
    private $mailTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $mailTranslationUuids, array $errors = [])
    {
        $this->mailTranslationUuids = $mailTranslationUuids;
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
    public function getMailTranslationUuids(): array
    {
        return $this->mailTranslationUuids;
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
