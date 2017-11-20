<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplate;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shop\Struct\ShopTemplateSearchResult;

class ShopTemplateSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
