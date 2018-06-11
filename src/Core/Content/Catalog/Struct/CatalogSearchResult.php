<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Struct;

use Shopware\Core\Content\Catalog\Collection\CatalogBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class CatalogSearchResult extends CatalogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
