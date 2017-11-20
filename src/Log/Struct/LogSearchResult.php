<?php declare(strict_types=1);

namespace Shopware\Log\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Log\Collection\LogBasicCollection;

class LogSearchResult extends LogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
