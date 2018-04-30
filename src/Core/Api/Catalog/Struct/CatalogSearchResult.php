<?php declare(strict_types=1);

namespace Shopware\Api\Catalog\Struct;

use Shopware\Api\Catalog\Collection\CatalogBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CatalogSearchResult extends CatalogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
