<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopFormTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'shop_form_translation.written';

    /**
     * @var string[]
     */
    private $shopFormTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $shopFormTranslationUuids, array $errors = [])
    {
        $this->shopFormTranslationUuids = $shopFormTranslationUuids;
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
    public function getShopFormTranslationUuids(): array
    {
        return $this->shopFormTranslationUuids;
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
