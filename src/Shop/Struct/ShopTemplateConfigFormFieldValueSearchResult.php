<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldValueBasicCollection;

class ShopTemplateConfigFormFieldValueSearchResult extends ShopTemplateConfigFormFieldValueBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
