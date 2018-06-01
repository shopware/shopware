<?php declare(strict_types=1);

namespace Shopware\System\Touchpoint\Event;

use Shopware\System\Touchpoint\Struct\TouchpointSearchResult;
use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;

class TouchpointSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'touchpoint.search.result.loaded';

    /**
     * @var TouchpointSearchResult
     */
    protected $result;

    public function __construct(TouchpointSearchResult $result)
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
