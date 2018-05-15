<?php declare(strict_types=1);

namespace Shopware\Content\Media\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Content\Media\Collection\MediaAlbumTranslationBasicCollection;

class MediaAlbumTranslationSearchResult extends MediaAlbumTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
