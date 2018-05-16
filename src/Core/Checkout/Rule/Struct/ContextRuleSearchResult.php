<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Struct;

use Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ContextRuleSearchResult extends ContextRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
