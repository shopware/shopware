<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopFormFieldTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'shop_form_field_translation.written';

    /**
     * @var string[]
     */
    private $shopFormFieldTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $shopFormFieldTranslationUuids, array $errors = [])
    {
        $this->shopFormFieldTranslationUuids = $shopFormFieldTranslationUuids;
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
    public function getShopFormFieldTranslationUuids(): array
    {
        return $this->shopFormFieldTranslationUuids;
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
