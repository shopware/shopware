<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmarketingVouchersWrittenEvent extends NestedEvent
{
    const NAME = 'emarketing_vouchers.written';

    /**
     * @var string[]
     */
    private $emarketingVouchersUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emarketingVouchersUuids, array $errors = [])
    {
        $this->emarketingVouchersUuids = $emarketingVouchersUuids;
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
    public function getEmarketingVouchersUuids(): array
    {
        return $this->emarketingVouchersUuids;
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
