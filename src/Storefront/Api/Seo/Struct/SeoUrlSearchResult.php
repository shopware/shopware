<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Storefront\Api\Seo\Collection\SeoUrlBasicCollection;

class SeoUrlSearchResult extends SeoUrlBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
