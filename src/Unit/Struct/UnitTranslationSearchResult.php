<?php declare(strict_types=1);

namespace Shopware\Unit\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Unit\Collection\UnitTranslationBasicCollection;

class UnitTranslationSearchResult extends UnitTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
