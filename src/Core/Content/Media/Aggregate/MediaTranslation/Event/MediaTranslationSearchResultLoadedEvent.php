<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaTranslation\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Media\Aggregate\MediaTranslation\Struct\MediaTranslationSearchResult;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
