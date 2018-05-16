<?php declare(strict_types=1);

namespace Shopware\System\Unit\Aggregate\UnitTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Unit\Aggregate\UnitTranslation\Collection\UnitTranslationBasicCollection;

class UnitTranslationSearchResult extends UnitTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
