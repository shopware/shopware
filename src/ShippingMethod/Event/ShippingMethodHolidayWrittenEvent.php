<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodHolidayWrittenEvent extends NestedEvent
{
    const NAME = 'shipping_method_holiday.written';

    /**
     * @var string[]
     */
    protected $shippingMethodHolidayUuids;

    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(array $shippingMethodHolidayUuids, TranslationContext $context, array $errors = [])
    {
        $this->shippingMethodHolidayUuids = $shippingMethodHolidayUuids;
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    /**
     * @return string[]
     */
    public function getShippingMethodHolidayUuids(): array
    {
        return $this->shippingMethodHolidayUuids;
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
