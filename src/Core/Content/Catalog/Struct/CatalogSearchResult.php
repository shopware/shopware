<?php declare(strict_types=1);

namespace Shopware\Content\Catalog\Struct;

use Shopware\Content\Catalog\Collection\CatalogBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CatalogSearchResult extends CatalogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
