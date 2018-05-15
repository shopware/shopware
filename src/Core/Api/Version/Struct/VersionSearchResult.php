<?php declare(strict_types=1);

namespace Shopware\Api\Version\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Api\Version\Collection\VersionBasicCollection;

class VersionSearchResult extends VersionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
