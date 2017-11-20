<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigFormFieldValue;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shop\Struct\ShopTemplateConfigFormFieldValueSearchResult;

class ShopTemplateConfigFormFieldValueSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form_field_value.search.result.loaded';

    /**
     * @var ShopTemplateConfigFormFieldValueSearchResult
     */
    protected $result;

    public function __construct(ShopTemplateConfigFormFieldValueSearchResult $result)
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
