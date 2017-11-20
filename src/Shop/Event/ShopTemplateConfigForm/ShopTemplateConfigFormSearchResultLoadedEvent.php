<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigForm;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shop\Struct\ShopTemplateConfigFormSearchResult;

class ShopTemplateConfigFormSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form.search.result.loaded';

    /**
     * @var ShopTemplateConfigFormSearchResult
     */
    protected $result;

    public function __construct(ShopTemplateConfigFormSearchResult $result)
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
