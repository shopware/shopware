<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Snippet\Struct\SnippetSearchResult;

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
