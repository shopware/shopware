<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue;

use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueDetailCollection;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormField\ShopTemplateConfigFormFieldBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopTemplateConfigFormFieldValueDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_form_field_value.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigFormFieldValueDetailCollection
     */
    protected $shopTemplateConfigFormFieldValues;

    public function __construct(ShopTemplateConfigFormFieldValueDetailCollection $shopTemplateConfigFormFieldValues, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigFormFieldValues = $shopTemplateConfigFormFieldValues;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
