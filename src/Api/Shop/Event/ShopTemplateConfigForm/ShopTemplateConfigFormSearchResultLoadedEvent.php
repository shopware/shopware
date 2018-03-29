<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigForm;

use Shopware\Api\Shop\Struct\ShopTemplateConfigFormSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateConfigFormSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_form.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
