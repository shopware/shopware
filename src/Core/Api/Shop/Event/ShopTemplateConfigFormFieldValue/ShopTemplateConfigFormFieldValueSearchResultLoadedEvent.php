<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue;

use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldValueSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateConfigFormFieldValueSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_form_field_value.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
