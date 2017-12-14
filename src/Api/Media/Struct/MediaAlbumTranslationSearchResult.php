<?php declare(strict_types=1);

namespace Shopware\Api\Media\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Media\Collection\MediaAlbumTranslationBasicCollection;

class MediaAlbumTranslationSearchResult extends MediaAlbumTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
