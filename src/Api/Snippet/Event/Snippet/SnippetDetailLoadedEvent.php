<?php declare(strict_types=1);

namespace Shopware\Api\Snippet\Event\Snippet;

use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Api\Snippet\Collection\SnippetDetailCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class SnippetDetailLoadedEvent extends NestedEvent
{
    const NAME = 'snippet.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var SnippetDetailCollection
     */
    protected $snippets;

    public function __construct(SnippetDetailCollection $snippets, TranslationContext $context)
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

    public function getSnippets(): SnippetDetailCollection
    {
        return $this->snippets;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->snippets->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->snippets->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
