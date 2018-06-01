<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Struct;

use Shopware\Core\Application\Context\Collection\ContextCartModifierBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ContextCartModifierTranslationSearchResult extends ContextCartModifierBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
