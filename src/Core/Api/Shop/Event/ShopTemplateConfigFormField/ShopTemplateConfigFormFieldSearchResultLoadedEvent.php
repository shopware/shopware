<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormField;

use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateConfigFormFieldSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_form_field.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
