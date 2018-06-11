<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Unit\Collection\UnitBasicCollection;

class UnitSearchResult extends UnitBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
