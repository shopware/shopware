<?php declare(strict_types=1);

namespace Shopware\Api\Context\Struct;

use Shopware\Api\Context\Collection\ContextCartModifierBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ContextCartModifierTranslationSearchResult extends ContextCartModifierBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
