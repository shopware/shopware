<?php declare(strict_types=1);

namespace Shopware\Api\Version\Struct;

use Shopware\Api\Version\Collection\VersionCommitBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class VersionCommitSearchResult extends VersionCommitBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
