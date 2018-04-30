<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Shop\Collection\ShopTemplateConfigPresetBasicCollection;

class ShopTemplateConfigPresetSearchResult extends ShopTemplateConfigPresetBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
