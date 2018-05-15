<?php declare(strict_types=1);

namespace Shopware\Application\Application\Struct;

use Shopware\Application\Application\Collection\ApplicationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ApplicationSearchResult extends ApplicationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
