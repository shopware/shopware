<?php declare(strict_types=1);

namespace Shopware\Application\Context\Struct;

use Shopware\Application\Context\Collection\ContextRuleBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ContextRuleSearchResult extends ContextRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
