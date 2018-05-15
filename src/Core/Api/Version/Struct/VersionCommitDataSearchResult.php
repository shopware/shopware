<?php declare(strict_types=1);

namespace Shopware\Api\Version\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Api\Version\Collection\VersionCommitDataBasicCollection;

class VersionCommitDataSearchResult extends VersionCommitDataBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
