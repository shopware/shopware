<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

class Trigger
{
    public const TIME_BEFORE = 'BEFORE';
    public const TIME_AFTER = 'AFTER';

    public const EVENT_INSERT = 'INSERT';
    public const EVENT_UPDATE = 'UPDATE';
    public const EVENT_DELETE = 'DELETE';

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $onTrigger;

    /**
     * @var string
     */
    private $time;

    /**
     * @var string
     */
    private $event;

    public function __construct(string $name, string $table, string $time, string $event, string $onTrigger)
    {
        $this->name = $name;
        $this->time = $this->validateArgumentTime($time);
        $this->event = $this->validateArgumentEvent($event);
        $this->table = $table;
        $this->onTrigger = $onTrigger;
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
