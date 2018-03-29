<?php

namespace Shopware\Api\Application\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Application\Collection\ApplicationBasicCollection;

class ApplicationSearchResult extends ApplicationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
