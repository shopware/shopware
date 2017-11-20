<?php declare(strict_types=1);

namespace Shopware\Snippet\Event\Snippet;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Snippet\Struct\SnippetSearchResult;

class SnippetSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'snippet.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
