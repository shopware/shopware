<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class UserShippingaddressWrittenEvent extends NestedEvent
{
    const NAME = 'user_shippingaddress.written';

    /**
     * @var string[]
     */
    private $userShippingaddressUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $userShippingaddressUuids, array $errors = [])
    {
        $this->userShippingaddressUuids = $userShippingaddressUuids;
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
    public function getUserShippingaddressUuids(): array
    {
        return $this->userShippingaddressUuids;
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
