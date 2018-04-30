<?php declare(strict_types=1);

namespace Shopware\Api\Context\Struct;

use Shopware\Api\Context\Collection\ContextRuleBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ContextRuleSearchResult extends ContextRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
