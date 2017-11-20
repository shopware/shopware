<?php declare(strict_types=1);

namespace Shopware\Media\Event\MediaTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Media\Struct\MediaTranslationSearchResult;

class MediaTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'media_translation.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
