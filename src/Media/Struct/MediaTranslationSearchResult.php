<?php declare(strict_types=1);

namespace Shopware\Media\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Media\Collection\MediaTranslationBasicCollection;

class MediaTranslationSearchResult extends MediaTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
