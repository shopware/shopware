<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigPreset;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Collection\ShopTemplateConfigPresetDetailCollection;
use Shopware\Shop\Event\ShopTemplate\ShopTemplateBasicLoadedEvent;

class ShopTemplateConfigPresetDetailLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_preset.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigPresetDetailCollection
     */
    protected $shopTemplateConfigPresets;

    public function __construct(ShopTemplateConfigPresetDetailCollection $shopTemplateConfigPresets, TranslationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigPresets = $shopTemplateConfigPresets;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShopTemplateConfigPresets(): ShopTemplateConfigPresetDetailCollection
    {
        return $this->shopTemplateConfigPresets;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shopTemplateConfigPresets->getShopTemplates()->count() > 0) {
            $events[] = new ShopTemplateBasicLoadedEvent($this->shopTemplateConfigPresets->getShopTemplates(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
