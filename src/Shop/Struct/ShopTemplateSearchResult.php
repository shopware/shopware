<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Shop\Collection\ShopTemplateBasicCollection;

class ShopTemplateSearchResult extends ShopTemplateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
