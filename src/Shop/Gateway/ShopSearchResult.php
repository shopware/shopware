<?php

namespace Shopware\Shop\Struct;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ShopSearchResult extends ShopIdentityCollection implements SearchResultInterface
{
    use SearchResultTrait;

    /**
     * @var ShopIdentity[]
     */
    protected $elements = [];

    public function __construct(array $elements, int $total)
    {
        parent::__construct($elements);
        $this->total = $total;
    }
}

