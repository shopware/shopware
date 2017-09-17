<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class PremiumProductWrittenEvent extends NestedEvent
{
    const NAME = 'premium_product.written';

    /**
     * @var string[]
     */
    private $premiumProductUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $premiumProductUuids, array $errors = [])
    {
        $this->premiumProductUuids = $premiumProductUuids;
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
    public function getPremiumProductUuids(): array
    {
        return $this->premiumProductUuids;
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
