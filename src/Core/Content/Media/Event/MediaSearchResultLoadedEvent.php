<?php declare(strict_types=1);

namespace Shopware\Content\Media\Event;

use Shopware\Framework\Context;
use Shopware\Content\Media\Struct\MediaSearchResult;
use Shopware\Framework\Event\NestedEvent;

class MediaSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'media.search.result.loaded';

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
