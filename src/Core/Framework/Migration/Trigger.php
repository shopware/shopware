<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Trigger
{
    final public const TIME_BEFORE = 'BEFORE';
    final public const TIME_AFTER = 'AFTER';

    final public const EVENT_INSERT = 'INSERT';
    final public const EVENT_UPDATE = 'UPDATE';
    final public const EVENT_DELETE = 'DELETE';

    private readonly string $time;

    private readonly string $event;

    public function __construct(
        private readonly string $name,
        private readonly string $table,
        string $time,
        string $event,
        private readonly string $onTrigger
    ) {
        $this->time = $this->validateArgumentTime($time);
        $this->event = $this->validateArgumentEvent($event);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getOnTrigger(): string
    {
        return $this->onTrigger;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    private function validateArgumentTime(string $time): string
    {
        if (!\in_array(
            $time,
            [
                self::TIME_AFTER,
                self::TIME_BEFORE,
            ],
            true
        )) {
            throw new \InvalidArgumentException('TriggerDefinition: argument time must be either \'BEFORE\' or \'AFTER\'');
        }

        return $time;
    }

    private function validateArgumentEvent(string $event): string
    {
        if (!\in_array(
            $event,
            [
                self::EVENT_INSERT,
                self::EVENT_UPDATE,
                self::EVENT_DELETE,
            ],
            true
        )) {
            throw new \InvalidArgumentException('TriggerDefinition: argument time must be either \'INSERT\', \'UPDATE\' or \'DELETE\'');
        }

        return $event;
    }
}
