<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint\Struct;

use Shopware\Core\System\Touchpoint\Collection\TouchpointBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class TouchpointSearchResult extends TouchpointBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
