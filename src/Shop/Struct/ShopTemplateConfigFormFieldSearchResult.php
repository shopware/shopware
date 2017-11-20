<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldBasicCollection;

class ShopTemplateConfigFormFieldSearchResult extends ShopTemplateConfigFormFieldBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
