<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Framework\ORM\Version\Collection\VersionCommitDataBasicCollection;

class VersionCommitDataSearchResult extends VersionCommitDataBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
