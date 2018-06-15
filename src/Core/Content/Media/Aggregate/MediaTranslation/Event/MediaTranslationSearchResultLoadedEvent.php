<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation\Event;

use Shopware\Core\Content\Media\Aggregate\MediaTranslation\Struct\MediaTranslationSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class MediaTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'media_translation.search.result.loaded';

    /**
     * @var MediaTranslationSearchResult
     */
    protected $result;

    public function __construct(MediaTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
