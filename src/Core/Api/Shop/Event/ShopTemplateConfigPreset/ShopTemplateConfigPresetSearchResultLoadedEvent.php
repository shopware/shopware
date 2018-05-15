<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigPreset;

use Shopware\Api\Shop\Struct\ShopTemplateConfigPresetSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateConfigPresetSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_preset.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
