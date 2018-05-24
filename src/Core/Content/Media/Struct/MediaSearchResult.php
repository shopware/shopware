<?php declare(strict_types=1);

namespace Shopware\Content\Media\Struct;

use Shopware\Content\Media\Collection\MediaBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class MediaSearchResult extends MediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
