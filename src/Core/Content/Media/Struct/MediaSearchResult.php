<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Struct;

use Shopware\Core\Content\Media\Collection\MediaBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class MediaSearchResult extends MediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
