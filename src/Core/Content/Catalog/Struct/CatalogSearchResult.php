<?php declare(strict_types=1);

namespace Shopware\Content\Catalog\Struct;

use Shopware\Content\Catalog\Collection\CatalogBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CatalogSearchResult extends CatalogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
