<?php declare(strict_types=1);

namespace Shopware\System\Unit\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Unit\Collection\UnitTranslationBasicCollection;

class UnitTranslationSearchResult extends UnitTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
