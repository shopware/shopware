<?php declare(strict_types=1);

namespace Shopware\Application\Context\Struct;

use Shopware\Application\Context\Collection\ContextCartModifierBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ContextCartModifierTranslationSearchResult extends ContextCartModifierBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
