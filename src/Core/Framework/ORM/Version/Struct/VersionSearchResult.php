<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\Framework\ORM\Version\Collection\VersionBasicCollection;

class VersionSearchResult extends VersionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
