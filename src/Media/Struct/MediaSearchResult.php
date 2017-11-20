<?php declare(strict_types=1);

namespace Shopware\Media\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Media\Collection\MediaBasicCollection;

class MediaSearchResult extends MediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
