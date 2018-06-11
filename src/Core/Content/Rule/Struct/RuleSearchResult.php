<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Struct;

use Shopware\Core\Content\Rule\Collection\RuleBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class RuleSearchResult extends RuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
