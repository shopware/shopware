<?php declare(strict_types=1);

namespace Shopware\Api\Snippet\Event\Snippet;

use Shopware\Api\Snippet\Collection\SnippetBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class SnippetBasicLoadedEvent extends NestedEvent
{
    const NAME = 'snippet.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var SnippetBasicCollection
     */
    protected $snippets;

    public function __construct(SnippetBasicCollection $snippets, TranslationContext $context)
    {
        $this->context = $context;
        $this->snippets = $snippets;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getSnippets(): SnippetBasicCollection
    {
        return $this->snippets;
    }
}
