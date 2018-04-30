<?php declare(strict_types=1);

namespace Shopware\Api\Application\Struct;

use Shopware\Api\Application\Collection\ApplicationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ApplicationSearchResult extends ApplicationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
