<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigPreset;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shop\Struct\ShopTemplateConfigPresetSearchResult;

class ShopTemplateConfigPresetSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_preset.search.result.loaded';

    /**
     * @var ShopTemplateConfigPresetSearchResult
     */
    protected $result;

    public function __construct(ShopTemplateConfigPresetSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
