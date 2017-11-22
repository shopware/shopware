<?php declare(strict_types=1);

namespace Shopware\Api\Version\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Version\Collection\VersionCommitBasicCollection;

class VersionCommitSearchResult extends VersionCommitBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
