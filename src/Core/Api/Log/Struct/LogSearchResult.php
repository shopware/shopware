<?php declare(strict_types=1);

namespace Shopware\Api\Log\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Log\Collection\LogBasicCollection;

class LogSearchResult extends LogBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
