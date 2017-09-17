<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopPageGroupMappingWrittenEvent extends NestedEvent
{
    const NAME = 'shop_page_group_mapping.written';

    /**
     * @var string[]
     */
    private $shopPageGroupMappingUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $shopPageGroupMappingUuids, array $errors = [])
    {
        $this->shopPageGroupMappingUuids = $shopPageGroupMappingUuids;
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
    public function getShopPageGroupMappingUuids(): array
    {
        return $this->shopPageGroupMappingUuids;
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
