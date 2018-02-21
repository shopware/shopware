<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigPreset;

use Shopware\Api\Shop\Collection\ShopTemplateConfigPresetDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplate\ShopTemplateBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopTemplateConfigPresetDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_preset.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigPresetDetailCollection
     */
    protected $shopTemplateConfigPresets;

    public function __construct(ShopTemplateConfigPresetDetailCollection $shopTemplateConfigPresets, ShopContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigPresets = $shopTemplateConfigPresets;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
