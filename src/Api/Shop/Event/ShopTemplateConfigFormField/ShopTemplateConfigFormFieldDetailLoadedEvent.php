<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormField;

use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplate\ShopTemplateBasicLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigForm\ShopTemplateConfigFormBasicLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopTemplateConfigFormFieldDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_form_field.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigFormFieldDetailCollection
     */
    protected $shopTemplateConfigFormFields;

    public function __construct(ShopTemplateConfigFormFieldDetailCollection $shopTemplateConfigFormFields, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigFormFields = $shopTemplateConfigFormFields;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getShopTemplateConfigFormFields(): ShopTemplateConfigFormFieldDetailCollection
    {
        return $this->shopTemplateConfigFormFields;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shopTemplateConfigFormFields->getShopTemplates()->count() > 0) {
            $events[] = new ShopTemplateBasicLoadedEvent($this->shopTemplateConfigFormFields->getShopTemplates(), $this->context);
        }
        if ($this->shopTemplateConfigFormFields->getShopTemplateConfigForms()->count() > 0) {
            $events[] = new ShopTemplateConfigFormBasicLoadedEvent($this->shopTemplateConfigFormFields->getShopTemplateConfigForms(), $this->context);
        }
        if ($this->shopTemplateConfigFormFields->getValues()->count() > 0) {
            $events[] = new ShopTemplateConfigFormFieldValueBasicLoadedEvent($this->shopTemplateConfigFormFields->getValues(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
