<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigForm;

use Shopware\Api\Shop\Collection\ShopTemplateConfigFormDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplate\ShopTemplateBasicLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormField\ShopTemplateConfigFormFieldBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopTemplateConfigFormDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_form.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigFormDetailCollection
     */
    protected $shopTemplateConfigForms;

    public function __construct(ShopTemplateConfigFormDetailCollection $shopTemplateConfigForms, TranslationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigForms = $shopTemplateConfigForms;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShopTemplateConfigForms(): ShopTemplateConfigFormDetailCollection
    {
        return $this->shopTemplateConfigForms;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shopTemplateConfigForms->getParents()->count() > 0) {
            $events[] = new ShopTemplateConfigFormBasicLoadedEvent($this->shopTemplateConfigForms->getParents(), $this->context);
        }
        if ($this->shopTemplateConfigForms->getShopTemplates()->count() > 0) {
            $events[] = new ShopTemplateBasicLoadedEvent($this->shopTemplateConfigForms->getShopTemplates(), $this->context);
        }
        if ($this->shopTemplateConfigForms->getChildren()->count() > 0) {
            $events[] = new ShopTemplateConfigFormBasicLoadedEvent($this->shopTemplateConfigForms->getChildren(), $this->context);
        }
        if ($this->shopTemplateConfigForms->getFields()->count() > 0) {
            $events[] = new ShopTemplateConfigFormFieldBasicLoadedEvent($this->shopTemplateConfigForms->getFields(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
