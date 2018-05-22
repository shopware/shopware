<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaAlbumTranslation\Struct;

use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class MediaAlbumTranslationSearchResult extends MediaAlbumTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
