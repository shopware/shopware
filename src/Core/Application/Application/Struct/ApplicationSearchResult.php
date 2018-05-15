<?php declare(strict_types=1);

namespace Shopware\Application\Application\Struct;

use Shopware\Application\Application\Collection\ApplicationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ApplicationSearchResult extends ApplicationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
