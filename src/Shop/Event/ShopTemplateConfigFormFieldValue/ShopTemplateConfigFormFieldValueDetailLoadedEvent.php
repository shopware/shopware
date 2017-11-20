<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigFormFieldValue;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldValueDetailCollection;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Shop\Event\ShopTemplateConfigFormField\ShopTemplateConfigFormFieldBasicLoadedEvent;

class ShopTemplateConfigFormFieldValueDetailLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form_field_value.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigFormFieldValueDetailCollection
     */
    protected $shopTemplateConfigFormFieldValues;

    public function __construct(ShopTemplateConfigFormFieldValueDetailCollection $shopTemplateConfigFormFieldValues, TranslationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigFormFieldValues = $shopTemplateConfigFormFieldValues;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShopTemplateConfigFormFieldValues(): ShopTemplateConfigFormFieldValueDetailCollection
    {
        return $this->shopTemplateConfigFormFieldValues;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shopTemplateConfigFormFieldValues->getShopTemplateConfigFormFields()->count() > 0) {
            $events[] = new ShopTemplateConfigFormFieldBasicLoadedEvent($this->shopTemplateConfigFormFieldValues->getShopTemplateConfigFormFields(), $this->context);
        }
        if ($this->shopTemplateConfigFormFieldValues->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->shopTemplateConfigFormFieldValues->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
