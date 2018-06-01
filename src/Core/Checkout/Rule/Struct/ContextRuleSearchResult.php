<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Rule\Struct;

use Shopware\Core\Checkout\Rule\Collection\ContextRuleBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ContextRuleSearchResult extends ContextRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
