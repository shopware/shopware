<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRule\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Tax\Aggregate\TaxAreaRule\Collection\TaxAreaRuleBasicCollection;

class TaxAreaRuleSearchResult extends TaxAreaRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
