<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigFormField;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shop\Struct\ShopTemplateConfigFormFieldSearchResult;

class ShopTemplateConfigFormFieldSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form_field.search.result.loaded';

    /**
     * @var ShopTemplateConfigFormFieldSearchResult
     */
    protected $result;

    public function __construct(ShopTemplateConfigFormFieldSearchResult $result)
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
