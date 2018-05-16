<?php declare(strict_types=1);

namespace Shopware\System\Log\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Log\Collection\LogBasicCollection;

class LogSearchResult extends LogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
