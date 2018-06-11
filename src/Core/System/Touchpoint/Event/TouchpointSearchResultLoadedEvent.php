<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint\Event;

use Shopware\Core\System\Touchpoint\Struct\TouchpointSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

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
