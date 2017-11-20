<?php declare(strict_types=1);

namespace Shopware\Media\Event\Media;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Media\Struct\MediaSearchResult;

class MediaSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'media.search.result.loaded';

    /**
     * @var MediaSearchResult
     */
    protected $result;

    public function __construct(MediaSearchResult $result)
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
