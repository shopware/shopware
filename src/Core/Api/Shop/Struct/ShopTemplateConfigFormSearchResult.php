<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormBasicCollection;

class ShopTemplateConfigFormSearchResult extends ShopTemplateConfigFormBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
