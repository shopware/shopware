<?php declare(strict_types=1);

namespace Shopware\System\Touchpoint\Struct;

use Shopware\System\Touchpoint\Collection\TouchpointBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class TouchpointSearchResult extends TouchpointBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
