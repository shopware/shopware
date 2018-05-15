<?php declare(strict_types=1);

namespace Shopware\Application\Context\Struct;

use Shopware\Application\Context\Collection\ContextRuleBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ContextRuleSearchResult extends ContextRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
