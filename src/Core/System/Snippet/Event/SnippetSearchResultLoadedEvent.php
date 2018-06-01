<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Snippet\Struct\SnippetSearchResult;

class SnippetSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'snippet.search.result.loaded';

    /**
     * @var SnippetSearchResult
     */
    protected $result;

    public function __construct(SnippetSearchResult $result)
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
