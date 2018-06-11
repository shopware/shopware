<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRule\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\Collection\TaxAreaRuleBasicCollection;

class TaxAreaRuleSearchResult extends TaxAreaRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
