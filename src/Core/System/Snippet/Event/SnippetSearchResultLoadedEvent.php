<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Event;

use Shopware\System\Snippet\Struct\SnippetSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
