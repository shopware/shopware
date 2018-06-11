<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Tax\Collection\TaxBasicCollection;

class TaxSearchResult extends TaxBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
