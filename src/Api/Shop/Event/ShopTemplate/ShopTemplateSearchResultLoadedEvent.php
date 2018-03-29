<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplate;

use Shopware\Api\Shop\Struct\ShopTemplateSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template.search.result.loaded';

    /**
     * @var ShopTemplateSearchResult
     */
    protected $result;

    public function __construct(ShopTemplateSearchResult $result)
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
