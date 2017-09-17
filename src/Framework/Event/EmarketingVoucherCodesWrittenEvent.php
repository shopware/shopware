<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmarketingVoucherCodesWrittenEvent extends NestedEvent
{
    const NAME = 'emarketing_voucher_codes.written';

    /**
     * @var string[]
     */
    private $emarketingVoucherCodesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emarketingVoucherCodesUuids, array $errors = [])
    {
        $this->emarketingVoucherCodesUuids = $emarketingVoucherCodesUuids;
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
    public function getEmarketingVoucherCodesUuids(): array
    {
        return $this->emarketingVoucherCodesUuids;
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
