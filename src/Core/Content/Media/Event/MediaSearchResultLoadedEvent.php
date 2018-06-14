<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Content\Media\Struct\MediaSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

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
