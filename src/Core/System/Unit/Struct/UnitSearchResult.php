<?php declare(strict_types=1);

namespace Shopware\System\Unit\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\System\Unit\Collection\UnitBasicCollection;

class UnitSearchResult extends UnitBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
