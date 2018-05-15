<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormBasicCollection;

class ShopTemplateConfigFormSearchResult extends ShopTemplateConfigFormBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
