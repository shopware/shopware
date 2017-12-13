<?php declare(strict_types=1);

namespace Shopware\Seo\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Seo\Collection\SeoUrlBasicCollection;

class SeoUrlSearchResult extends SeoUrlBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
