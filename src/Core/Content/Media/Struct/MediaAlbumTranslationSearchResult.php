<?php declare(strict_types=1);

namespace Shopware\Content\Media\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Media\Collection\MediaAlbumTranslationBasicCollection;

class MediaAlbumTranslationSearchResult extends MediaAlbumTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
