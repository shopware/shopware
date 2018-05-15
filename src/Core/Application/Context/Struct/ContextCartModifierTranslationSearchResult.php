<?php declare(strict_types=1);

namespace Shopware\Application\Context\Struct;

use Shopware\Application\Context\Collection\ContextCartModifierBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ContextCartModifierTranslationSearchResult extends ContextCartModifierBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
